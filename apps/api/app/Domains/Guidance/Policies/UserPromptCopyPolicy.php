<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Models\UserPromptCopy;
use App\Domains\Users\Models\User;

final class UserPromptCopyPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, UserPromptCopy $copy): bool
    {
        return $this->owns($user, $copy);
    }

    public function update(User $user, UserPromptCopy $copy): bool
    {
        return $this->owns($user, $copy);
    }

    private function owns(User $user, UserPromptCopy $copy): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $copy->user_id;
    }
}
