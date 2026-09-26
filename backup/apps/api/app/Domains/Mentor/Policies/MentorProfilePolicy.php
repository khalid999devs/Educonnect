<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Policies;

use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Mentor\Models\MentorProfile;
use App\Domains\Users\Models\User;

final class MentorProfilePolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole(RoleKey::Mentor);
    }

    public function update(User $user, MentorProfile $profile): bool
    {
        return $user->hasRole(RoleKey::Mentor)
            && (int) $user->getKey() === (int) $profile->user_id;
    }
}
