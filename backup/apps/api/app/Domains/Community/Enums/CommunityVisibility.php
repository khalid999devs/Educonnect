<?php

declare(strict_types=1);

namespace App\Domains\Community\Enums;

enum CommunityVisibility: string
{
    case Published = 'published';
    case Archived = 'archived';
}
