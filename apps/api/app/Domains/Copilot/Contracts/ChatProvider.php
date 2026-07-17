<?php

declare(strict_types=1);

namespace App\Domains\Copilot\Contracts;

/**
 * Text-reply provider boundary for the Copilot. Implementations receive the
 * fully assembled message list (system guardrails + bounded context +
 * bounded history + the user turn) and return plain reply text, which the
 * caller bounds and sanitizes before returning it to the client.
 */
interface ChatProvider
{
    public function name(): string;

    public function model(): string;

    /** @param list<array{role: string, content: string}> $messages */
    public function reply(array $messages): string;
}
