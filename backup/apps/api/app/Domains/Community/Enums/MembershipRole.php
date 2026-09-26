<?php

declare(strict_types=1);

namespace App\Domains\Community\Enums;

enum MembershipRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
}
