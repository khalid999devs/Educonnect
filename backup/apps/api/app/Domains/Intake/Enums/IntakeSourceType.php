<?php

declare(strict_types=1);

namespace App\Domains\Intake\Enums;

enum IntakeSourceType: string
{
    case File = 'file';
    case Link = 'link';
}
