<?php

declare(strict_types=1);

namespace App\Domains\Tools\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Telemetry\Enums\TelemetryOutcome;
use App\Domains\Tools\AI\ScenarioSearchPolicy;
use App\Domains\Tools\Contracts\ScenarioRanker;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Tools\Support\ScenarioSearchOutcome;
use App\Domains\Users\Models\User;
use App\Support\Ai\AiAgentRunner;
use App\Support\Ai\AiFeature;
use App\Support\Ai\Exceptions\AiFeatureDisabled;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Scenario tool search, end to end.
 *
 * Retrieval is Postgres and only Postgres: ListPublishedTools applies
 * PublishedToolVisibility, the optional category filter, and a hard candidate
 * cap. The AI layer never sees a catalog it could search - it sees a finite set
 * it may reorder. That is what makes a hallucinated or cross-tenant tool
 * impossible to render, and it is why every failure mode here still returns a
 * useful answer rather than an error.
 *
 * The scenario itself is never passed to the SQL LIKE filter. A sentence like
 * "I have an exam in three days" matches no curated field, so using it as a
 * search term would return nothing; keyword relevance is the ranker's job.
 */
final readonly class RankToolsForScenario
{
    private const CACHE_PREFIX = 'tools:scenario:';

    public function __construct(
        private ListPublishedTools $tools,
        private ScenarioSearchPolicy $policy,
        private AiAgentRunner $runner,
    ) {}

    public function execute(User $user, string $scenario, ?string $category): ScenarioSearchOutcome
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        $candidates = $this->candidates($user, $category);
        $model = AiFeature::ToolScenario->model();
        $fallback = $this->policy->fallback();

        if ($candidates === []) {
            return new ScenarioSearchOutcome([], $fallback->name(), $model, false, false);
        }

        $payload = $this->payload($candidates);
        $cacheKey = $this->cacheKey($scenario, $category, array_column($payload, 'public_id'));
        $primary = $this->policy->primary();
        $hasRemotePrimary = $this->policy->hasRemotePrimary();
        $cached = $this->cachedRankings($cacheKey);

        if ($cached !== null) {
            // Served without a provider call, a schema pass, or a second query.
            return new ScenarioSearchOutcome($this->order($candidates, $cached), $primary->name(), $model, true, true);
        }

        try {
            $result = $this->runner->run(
                AiFeature::ToolScenario,
                $primary->name(),
                static fn (): array => $primary->rank($scenario, $payload),
                $hasRemotePrimary ? static fn (): array => $fallback->rank($scenario, $payload) : null,
                $fallback->name(),
                $fallback->model(),
            );
        } catch (AiFeatureDisabled) {
            // The kill switch is off. Scenario search is an organization
            // feature, so it degrades to the deterministic ranker rather than
            // to a 503.
            return $this->deterministic($candidates, $scenario, $payload, $fallback, $model);
        } catch (Throwable) {
            // Only reachable when the deterministic ranker is itself the
            // primary and threw. Return the candidate set in its Postgres order
            // rather than failing a read the student can still use.
            return new ScenarioSearchOutcome(
                $this->unexplained($candidates),
                $fallback->name(),
                $model,
                false,
                false,
            );
        }

        $rankings = $this->rankings($result->value);
        $aiRanked = $hasRemotePrimary && $result->outcome === TelemetryOutcome::Success;

        // Only a genuine AI ranking is cached. Pinning a degraded deterministic
        // ordering for fifteen minutes would outlast the outage that caused it.
        if ($aiRanked && $rankings !== []) {
            Cache::store()->put($cacheKey, $rankings, $this->policy->cacheTtlSeconds());
        }

        return new ScenarioSearchOutcome(
            $this->order($candidates, $rankings),
            $result->provider,
            $model,
            $aiRanked,
            false,
        );
    }

    /**
     * @param  list<Tool>  $candidates
     * @param  list<array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}>  $payload
     */
    private function deterministic(
        array $candidates,
        string $scenario,
        array $payload,
        ScenarioRanker $fallback,
        string $model,
    ): ScenarioSearchOutcome {
        return new ScenarioSearchOutcome(
            $this->order($candidates, $fallback->rank($scenario, $payload)),
            $fallback->name(),
            $model,
            false,
            false,
        );
    }

    /** @return list<Tool> */
    private function candidates(User $user, ?string $category): array
    {
        $paginator = $this->tools->execute(
            $user,
            null,
            $category,
            'all',
            'name',
            $this->policy->maxCandidates(),
        );

        return array_values($paginator->items());
    }

    /**
     * @param  list<Tool>  $candidates
     * @return list<array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}>
     */
    private function payload(array $candidates): array
    {
        return array_map(static function (Tool $tool): array {
            $category = $tool->relationLoaded('category') ? $tool->getRelation('category') : null;
            $useCases = $tool->getAttribute('use_cases');

            return [
                'public_id' => (string) $tool->public_id,
                'name' => (string) $tool->name,
                'category' => $category instanceof ToolCategory ? (string) $category->name : '',
                'purpose' => (string) $tool->purpose,
                'use_cases' => is_array($useCases)
                    ? array_values(array_filter($useCases, is_string(...)))
                    : [],
            ];
        }, $candidates);
    }

    /**
     * Re-projects a ranking onto the models already in memory. Only candidates
     * the ranker actually returned survive: an AI ranking that omits a tool is
     * saying it does not help with this scenario, and a search result set that
     * silently re-adds everything is not a search.
     *
     * @param  list<Tool>  $candidates
     * @param  list<array{public_id: string, match_reason: string}>  $rankings
     * @return list<Tool>
     */
    private function order(array $candidates, array $rankings): array
    {
        $byId = [];

        foreach ($candidates as $tool) {
            $byId[(string) $tool->public_id] = $tool;
        }

        $ordered = [];

        foreach ($rankings as $ranking) {
            $tool = $byId[$ranking['public_id']] ?? null;

            if (! $tool instanceof Tool) {
                continue;
            }

            unset($byId[$ranking['public_id']]);
            $tool->setAttribute('match_reason', $ranking['match_reason']);
            $ordered[] = $tool;
        }

        return $ordered;
    }

    /**
     * @param  list<Tool>  $candidates
     * @return list<Tool>
     */
    private function unexplained(array $candidates): array
    {
        foreach ($candidates as $tool) {
            $tool->setAttribute('match_reason', 'Review whether this tool fits your scenario.');
        }

        return $candidates;
    }

    /**
     * @return list<array{public_id: string, match_reason: string}>
     */
    private function rankings(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $rankings = [];

        foreach ($value as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $publicId = $entry['public_id'] ?? null;
            $reason = $entry['match_reason'] ?? null;

            if (is_string($publicId) && is_string($reason)) {
                $rankings[] = ['public_id' => $publicId, 'match_reason' => $reason];
            }
        }

        return $rankings;
    }

    /**
     * @return list<array{public_id: string, match_reason: string}>|null
     */
    private function cachedRankings(string $key): ?array
    {
        $cached = Cache::store()->get($key);

        if (! is_array($cached)) {
            return null;
        }

        $rankings = $this->rankings($cached);

        return $rankings === [] ? null : $rankings;
    }

    /**
     * The key binds the normalised scenario to the exact candidate id set and
     * category, so a newly published, archived, or re-curated tool changes the
     * key and can never be served a stale ranking that omits it.
     *
     * @param  list<string>  $candidateIds
     */
    private function cacheKey(string $scenario, ?string $category, array $candidateIds): string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($scenario)));

        return self::CACHE_PREFIX.hash(
            'sha256',
            implode("\n", [$normalized, $category ?? '', implode(',', $candidateIds)]),
        );
    }
}
