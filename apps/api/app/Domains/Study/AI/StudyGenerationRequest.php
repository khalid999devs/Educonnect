<?php

declare(strict_types=1);

namespace App\Domains\Study\AI;

use App\Domains\Study\Enums\StudyArtifactKind;

/**
 * Everything a generator is allowed to see about one study request. The
 * extracted text is already bounded by the caller to
 * `ai.features.<feature>.max_context_characters` (24,000) - deliberately NOT
 * `intake.max_extracted_characters` (200,000), which is two orders of magnitude
 * too large for a prompt.
 */
final readonly class StudyGenerationRequest
{
    public function __construct(
        public StudyArtifactKind $kind,
        public string $title,
        public string $extractedText,
        public ?string $sourceUrl = null,
        public int $maxQuestions = 20,
    ) {}
}
