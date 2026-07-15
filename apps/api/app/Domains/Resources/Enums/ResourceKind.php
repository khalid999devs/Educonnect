<?php

declare(strict_types=1);

namespace App\Domains\Resources\Enums;

enum ResourceKind: string
{
    case Link = 'link';
    case File = 'file';
}
