<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeSuggestionStatus: string
{
    case Proposed = 'proposed';
    case Dismissed = 'dismissed';
    case Applied = 'applied';
}
