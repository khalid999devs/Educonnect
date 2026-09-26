<?php

declare(strict_types=1);

namespace App\Domains\Tools\AI;

use App\Domains\Tools\Contracts\ScenarioRanker;
use App\Domains\Tools\Exceptions\InvalidScenarioRanking;
use App\Domains\Tools\Queries\BuildScenarioSearchMessages;
use App\Support\Ai\AiFeature;
use App\Support\Ai\Exceptions\InvalidAiOutput;
use App\Support\Ai\OpenAiClient;
use JsonException;
use RuntimeException;

/**
 * OpenAI-backed scenario re-ranking.
 *
 * The agent owns transport and bounded output retries and nothing else: the
 * prompt lives in BuildScenarioSearchMessages, validation in
 * ScenarioRankingSchemaV1, and the kill switch, breaker, timing, telemetry and
 * fallback ordering in AiAgentRunner - which is the only thing that ever calls
 * this class.
 *
 * Retry semantics follow the intake classifier exactly and must not be
 * conflated: an InvalidAiOutput means the provider is healthy and its answer
 * was malformed, so retrying is worthwhile; any other Throwable means the
 * provider itself is unhealthy, so it is abandoned immediately and the runner's
 * deterministic fallback answers instead.
 */
final readonly class ScenarioSearchAgent implements ScenarioRanker
{
    public function __construct(
        private OpenAiClient $client,
        private BuildScenarioSearchMessages $messages,
        private ScenarioRankingSchemaV1 $schema,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return AiFeature::ToolScenario->model();
    }

    /**
     * @param  list<array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}>  $candidates
     * @return list<array{public_id: string, match_reason: string}>
     */
    public function rank(string $scenario, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        $candidateIds = array_column($candidates, 'public_id');
        $messages = $this->messages->execute($scenario, $candidates);
        $attempts = AiFeature::ToolScenario->limit('max_output_retries', 1) + 1;
        $lastRejection = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $this->schema->validate($this->completion($messages), $candidateIds);
            } catch (InvalidAiOutput $rejection) {
                $lastRejection = $rejection;
            }
        }

        throw $lastRejection ?? new InvalidScenarioRanking('the provider returned no usable ranking');
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function completion(array $messages): array
    {
        $content = $this->client->chat($this->model(), $messages, jsonObject: true, feature: AiFeature::ToolScenario);

        try {
            $decoded = json_decode($content, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenAI returned invalid JSON.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('OpenAI returned a non-object payload.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
