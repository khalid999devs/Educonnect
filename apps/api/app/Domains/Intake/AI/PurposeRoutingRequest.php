<?php

declare(strict_types=1);

namespace App\Domains\Intake\AI;

use App\Domains\Intake\Enums\IntakeSourceType;

/**
 * The bounded, already-sanitised input every purpose router reads. The job
 * assembles it once so the deterministic and remote routers see byte-identical
 * evidence and a disagreement is a routing difference, not an input difference.
 */
final readonly class PurposeRoutingRequest
{
    public function __construct(
        public string $extractedText,
        public IntakeSourceType $sourceType,
        public ?string $context,
        public ?string $sourceUrl,
        public ?string $fileName,
        public ?string $mimeType,
    ) {}
}
