<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Guidance\Models\UserPromptPreference;
use App\Domains\Users\Models\User;

final class UserPromptPreferencePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, UserPromptPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function update(User $user, UserPromptPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function delete(User $user, UserPromptPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    private function owns(User $user, UserPromptPreference $preference): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $preference->user_id;
    }
}
