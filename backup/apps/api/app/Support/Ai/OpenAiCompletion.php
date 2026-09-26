<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * One completion plus the token counts needed for cost accounting. Usage is
 * reported by the provider and may be absent, so both counts are nullable and
 * are never treated as authoritative billing figures.
 */
final readonly class OpenAiCompletion
{
    public function __construct(
        public string $content,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
    ) {}

    public function totalTokens(): ?int
    {
        if ($this->promptTokens === null && $this->completionTokens === null) {
            return null;
        }

        return ($this->promptTokens ?? 0) + ($this->completionTokens ?? 0);
    }
}
