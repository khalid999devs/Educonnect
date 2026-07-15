<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Enums;

enum GuidancePreferenceState: string
{
    case Saved = 'saved';
    case Dismissed = 'dismissed';
}
