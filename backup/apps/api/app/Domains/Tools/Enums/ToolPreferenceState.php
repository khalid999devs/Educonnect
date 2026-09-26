<?php

declare(strict_types=1);

namespace App\Domains\Tools\Enums;

enum ToolPreferenceState: string
{
    case Saved = 'saved';
    case Dismissed = 'dismissed';
}
