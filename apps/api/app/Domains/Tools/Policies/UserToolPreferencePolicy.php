<?php

declare(strict_types=1);

namespace App\Domains\Tools\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Tools\Models\UserToolPreference;
use App\Domains\Users\Models\User;

final class UserToolPreferencePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, UserToolPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function update(User $user, UserToolPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function delete(User $user, UserToolPreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    private function owns(User $user, UserToolPreference $preference): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $preference->user_id;
    }
}
