<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Contracts;

use App\Domains\Users\Models\User;

interface ModerationTarget
{
    public function isInModerationScopeFor(User $moderator): bool;
}
