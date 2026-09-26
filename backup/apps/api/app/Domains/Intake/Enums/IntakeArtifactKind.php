<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeArtifactKind: string
{
    case AcquiredContent = 'acquired_content';
    case ExtractedText = 'extracted_text';
}
