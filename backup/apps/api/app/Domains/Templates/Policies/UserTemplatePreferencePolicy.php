<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Models\UserTemplatePreference;
use App\Domains\Users\Models\User;

final class UserTemplatePreferencePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, UserTemplatePreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function update(User $user, UserTemplatePreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    public function delete(User $user, UserTemplatePreference $preference): bool
    {
        return $this->owns($user, $preference);
    }

    private function owns(User $user, UserTemplatePreference $preference): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $preference->user_id;
    }
}
