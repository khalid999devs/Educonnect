<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Enums;

enum KnowledgeSourceType: string
{
    case Resource = 'resource';
    case Link = 'link';
    case None = 'none';
}
