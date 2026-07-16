<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

final readonly class ClassificationRequest
{
    /**
     * @param  list<array{public_id: string, title: string, code: string|null}>  $courses
     */
    public function __construct(
        public string $extractedText,
        public ?string $context,
        public ?string $sourceUrl,
        public array $courses,
        public int $maxSuggestions,
    ) {}
}
