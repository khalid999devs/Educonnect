<?php

declare(strict_types=1);

namespace App\Domains\Authorization\Policies;

use App\Domains\Authorization\Contracts\ModerationTarget;
use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Models\User;

final class ModerationPolicy
{
    public function moderate(User $user, ModerationTarget $target): bool
    {
        if ($user->hasCapability(CapabilityKey::ModerationGlobal)) {
            return true;
        }

        return $user->hasCapability(CapabilityKey::ModerationScoped)
            && $target->isInModerationScopeFor($user);
    }
}
