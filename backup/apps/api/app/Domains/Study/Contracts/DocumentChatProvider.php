<?php

declare(strict_types=1);

namespace App\Domains\Study\Contracts;

/**
 * The trust boundary for grounded document chat.
 *
 * An implementation receives a fully assembled message list - system
 * guardrails, the bounded document context, the client-held history, then the
 * user turn - and returns plain reply text. It never reads a record, never
 * fetches context of its own, and never decides what the student may see:
 * ownership and bounding are settled by BuildDocumentChatMessages before this
 * boundary is crossed.
 *
 * This is deliberately a SIBLING of App\Domains\Copilot\Contracts\ChatProvider,
 * not a merge of it. The two look alike today and must not be unified: each is
 * the swap point its own feature test suite binds a fake against, and the
 * Copilot contract is the doc-12 / ADR-0021 trust boundary for advisory
 * workspace chat, which answers from a dashboard snapshot rather than from one
 * document. Collapsing them would couple two independently switchable
 * capabilities to a single seam and let a change to one silently retune the
 * other.
 *
 * Document chat has NO deterministic fallback by design. A fabricated answer
 * about a student's own source material is strictly worse than no answer, so
 * this capability degrades honestly to a 503 rather than to invented text.
 */
interface DocumentChatProvider
{
    /** The provider identity recorded in telemetry (never a model name). */
    public function name(): string;

    /** The approved model id, read from the AiFeature model policy. */
    public function model(): string;

    /** @param list<array{role: string, content: string}> $messages */
    public function reply(array $messages): string;
}
