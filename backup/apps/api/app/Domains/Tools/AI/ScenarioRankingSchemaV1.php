<?php

declare(strict_types=1);

namespace App\Domains\Tools\AI;

use App\Domains\Tools\Exceptions\InvalidScenarioRanking;
use App\Support\Ai\BoundedText;
use InvalidArgumentException;

/**
 * Versioned structured-output schema for scenario tool search. Every provider
 * response passes through this validator before anything is rendered.
 *
 * The load-bearing rule is the candidate-set check: a public_id that was not in
 * the set this request supplied is rejected outright. That single rule makes a
 * hallucinated tool, a stale tool, an unpublished tool, and another tenant's
 * tool all impossible to surface - including when a prompt-injected scenario
 * convinced the provider to emit one.
 */
final class ScenarioRankingSchemaV1
{
    public const VERSION = 'v1';

    /** One sentence; long enough to explain a fit, short enough to render in a card. */
    public const MAX_REASON_CHARACTERS = 200;

    private const ALLOWED_KEYS = ['public_id', 'match_reason'];

    /**
     * @param  array<string, mixed>  $raw  the decoded provider payload
     * @param  list<string>  $candidateIds  the only ids this response may reference
     * @return list<array{public_id: string, match_reason: string}>
     */
    public function validate(array $raw, array $candidateIds): array
    {
        $rankings = $raw['rankings'] ?? null;

        if (! is_array($rankings) || array_keys($raw) !== ['rankings'] || ! array_is_list($rankings)) {
            throw new InvalidScenarioRanking('the payload must contain only a rankings list');
        }

        if (count($rankings) > count($candidateIds)) {
            throw new InvalidScenarioRanking('more rankings than candidates were returned');
        }

        $seen = [];
        $validated = [];

        foreach ($rankings as $ranking) {
            if (! is_array($ranking)) {
                throw new InvalidScenarioRanking('a ranking must be an object');
            }

            if (array_diff(array_keys($ranking), self::ALLOWED_KEYS) !== []) {
                throw new InvalidScenarioRanking('a ranking contains unknown keys');
            }

            $publicId = $ranking['public_id'] ?? null;

            // The re-ranker contract, enforced: a model may reorder the
            // candidate set and nothing else.
            if (! is_string($publicId) || ! in_array($publicId, $candidateIds, true)) {
                throw new InvalidScenarioRanking('a ranking referenced a tool outside the candidate set');
            }

            if (isset($seen[$publicId])) {
                throw new InvalidScenarioRanking('a tool was ranked more than once');
            }

            $seen[$publicId] = true;

            $validated[] = [
                'public_id' => $publicId,
                'match_reason' => $this->boundedReason($ranking['match_reason'] ?? null),
            ];
        }

        return $validated;
    }

    /**
     * Adapts the shared bounding rule to this schema's rejection message. The
     * rule itself lives once, in BoundedText: trimmed, non-empty, within the
     * bound, no control characters, no angle brackets.
     */
    private function boundedReason(mixed $value): string
    {
        if (! is_string($value)) {
            throw new InvalidScenarioRanking('the match reason must be a string');
        }

        try {
            return BoundedText::plainText($value, self::MAX_REASON_CHARACTERS);
        } catch (InvalidArgumentException) {
            throw new InvalidScenarioRanking('the match reason must be bounded plain text');
        }
    }
}
