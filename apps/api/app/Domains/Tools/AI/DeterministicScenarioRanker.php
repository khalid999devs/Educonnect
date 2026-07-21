<?php

declare(strict_types=1);

namespace App\Domains\Tools\AI;

use App\Domains\Tools\Contracts\ScenarioRanker;
use App\Support\Ai\BoundedText;

/**
 * The always-available scenario ranker: no network, no provider, no key.
 *
 * It scores keyword overlap between the student's scenario and each candidate's
 * curated fields, weighted so a name match outranks a use-case mention. Because
 * retrieval already happened in Postgres, this ranker never drops a candidate -
 * it only reorders - which is exactly the "unranked Postgres candidate set"
 * degradation the architecture promises when the AI path is off, disabled, or
 * short-circuited by an open breaker.
 *
 * Tokens are reduced to bare alphanumerics before they are ever echoed into a
 * match reason, so an injected `<script>` in a scenario can only ever come back
 * out as the word "script".
 */
final readonly class DeterministicScenarioRanker implements ScenarioRanker
{
    /** Enough signal to rank on without letting one long scenario dominate. */
    private const MAX_TOKENS = 24;

    private const MIN_TOKEN_LENGTH = 3;

    private const MAX_REPORTED_TERMS = 4;

    /**
     * Common academic filler that matches everything and therefore ranks
     * nothing. Kept small and literal on purpose; this is not a stemmer.
     */
    private const STOP_WORDS = [
        'and', 'are', 'but', 'can', 'day', 'days', 'for', 'from', 'get', 'got', 'has', 'have',
        'help', 'how', 'into', 'its', 'need', 'needs', 'not', 'now', 'out', 'over', 'sec',
        'some', 'that', 'the', 'their', 'them', 'then', 'there', 'these', 'they', 'this',
        'time', 'tool', 'tools', 'use', 'using', 'want', 'was', 'week', 'weeks', 'what',
        'when', 'which', 'will', 'with', 'work', 'you', 'your',
    ];

    public function name(): string
    {
        return 'deterministic';
    }

    public function model(): string
    {
        return 'deterministic';
    }

    /**
     * @param  list<array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}>  $candidates
     * @return list<array{public_id: string, match_reason: string}>
     */
    public function rank(string $scenario, array $candidates): array
    {
        $tokens = $this->tokenize($scenario);
        $scored = [];

        foreach ($candidates as $position => $candidate) {
            [$score, $matched] = $this->score($candidate, $tokens);

            $scored[] = [
                'position' => $position,
                'score' => $score,
                'public_id' => $candidate['public_id'],
                'match_reason' => $this->reason($candidate, $matched),
            ];
        }

        // Score first, then the Postgres order, so an all-zero scenario keeps
        // the deterministic alphabetical candidate order rather than shuffling.
        usort(
            $scored,
            static fn (array $left, array $right): int => $right['score'] <=> $left['score']
                ?: $left['position'] <=> $right['position'],
        );

        return array_map(
            static fn (array $entry): array => [
                'public_id' => $entry['public_id'],
                'match_reason' => $entry['match_reason'],
            ],
            $scored,
        );
    }

    /**
     * @param  array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}  $candidate
     * @param  list<string>  $tokens
     * @return array{0: int, 1: list<string>}
     */
    private function score(array $candidate, array $tokens): array
    {
        $fields = [
            3 => mb_strtolower($candidate['name']),
            2 => mb_strtolower($candidate['category']),
            1 => mb_strtolower($candidate['purpose'].' '.implode(' ', $candidate['use_cases'])),
        ];

        $score = 0;
        $matched = [];

        foreach ($tokens as $token) {
            foreach ($fields as $weight => $haystack) {
                if (str_contains($haystack, $token)) {
                    $score += $weight;
                    $matched[$token] = true;
                }
            }
        }

        return [$score, array_keys($matched)];
    }

    /**
     * @param  array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}  $candidate
     * @param  list<string>  $matched
     */
    private function reason(array $candidate, array $matched): string
    {
        $category = BoundedText::titleText($candidate['category'], 60, 'the catalog');

        $reason = $matched === []
            ? "Curated under {$category}; review whether it fits your scenario."
            : 'Matches your scenario on: '.implode(', ', array_slice($matched, 0, self::MAX_REPORTED_TERMS)).'.';

        return BoundedText::titleText(
            $reason,
            ScenarioRankingSchemaV1::MAX_REASON_CHARACTERS,
            'Review whether this tool fits your scenario.',
        );
    }

    /** @return list<string> */
    private function tokenize(string $scenario): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($scenario)) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            if (mb_strlen($part) < self::MIN_TOKEN_LENGTH
                || in_array($part, self::STOP_WORDS, true)
                || preg_match('/^[a-z0-9]+$/D', $part) !== 1) {
                continue;
            }

            $tokens[$part] = true;

            if (count($tokens) >= self::MAX_TOKENS) {
                break;
            }
        }

        return array_keys($tokens);
    }
}
