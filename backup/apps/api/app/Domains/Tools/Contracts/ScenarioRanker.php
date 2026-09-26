<?php

declare(strict_types=1);

namespace App\Domains\Tools\Contracts;

/**
 * The trust boundary for scenario-driven tool search.
 *
 * A ranker is a RE-RANKER, never a retriever. Retrieval stays in Postgres
 * (ListPublishedTools): the caller hands over a bounded candidate set that has
 * already passed PublishedToolVisibility, and an implementation may only
 * reorder and explain it. Returning an id that was not in the candidate set is
 * a contract violation, and ScenarioRankingSchemaV1 rejects it, so a
 * hallucinated or cross-tenant tool is structurally impossible rather than
 * merely unlikely.
 *
 * Embeddings and vector retrieval are out of scope by ADR-0015 and doc 12; a
 * new ADR is required before that changes.
 */
interface ScenarioRanker
{
    /** The provider identity recorded in telemetry (never a model name). */
    public function name(): string;

    /** The approved model id, or a deterministic marker for non-AI rankers. */
    public function model(): string;

    /**
     * Order the candidate tools against the student's scenario and explain each
     * match in one bounded plain-text sentence.
     *
     * @param  list<array{public_id: string, name: string, category: string, purpose: string, use_cases: list<string>}>  $candidates
     * @return list<array{public_id: string, match_reason: string}>
     */
    public function rank(string $scenario, array $candidates): array;
}
