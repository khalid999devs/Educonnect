<?php

declare(strict_types=1);

namespace App\Domains\Users\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}
