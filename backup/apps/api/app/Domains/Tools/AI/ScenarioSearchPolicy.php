<?php

declare(strict_types=1);

namespace App\Domains\Tools\AI;

use App\Domains\Tools\Contracts\ScenarioRanker;
use App\Support\Ai\AiFeature;
use Illuminate\Contracts\Foundation\Application;

/**
 * Selects the approved ranker chain for scenario tool search: the configured
 * primary first, then the deterministic keyword ranker, so a failing, disabled,
 * or short-circuited provider never leaves a student staring at an empty search.
 *
 * Mirrors ClassificationPolicy deliberately, including the collapse case: when
 * OpenAI is unconfigured the container binds the deterministic ranker as the
 * primary, and the chain must not then list it twice - a duplicated fallback
 * would record a pointless second telemetry row for a call that never failed.
 */
final readonly class ScenarioSearchPolicy
{
    /** The hard ceiling ListPublishedTools accepts for a single page. */
    private const CANDIDATE_CEILING = 50;

    public function __construct(private Application $app) {}

    public function primary(): ScenarioRanker
    {
        return $this->app->make(ScenarioRanker::class);
    }

    public function fallback(): DeterministicScenarioRanker
    {
        return $this->app->make(DeterministicScenarioRanker::class);
    }

    /**
     * True when the primary is a genuinely different implementation from the
     * fallback, and therefore worth running with a fallback behind it.
     */
    public function hasRemotePrimary(): bool
    {
        return $this->primary()->name() !== $this->fallback()->name();
    }

    /** How many candidates Postgres retrieval may hand to a ranker. */
    public function maxCandidates(): int
    {
        return min(self::CANDIDATE_CEILING, AiFeature::ToolScenario->limit('max_candidates', 50));
    }

    public function maxScenarioCharacters(): int
    {
        return AiFeature::ToolScenario->limit('max_scenario_characters', 600);
    }

    /**
     * Scenario search is latency-critical - a student is watching a search box -
     * so an identical scenario over an identical candidate set is answered from
     * cache rather than re-billed to the provider.
     */
    public function cacheTtlSeconds(): int
    {
        return AiFeature::ToolScenario->limit('cache_ttl_seconds', 900);
    }
}
