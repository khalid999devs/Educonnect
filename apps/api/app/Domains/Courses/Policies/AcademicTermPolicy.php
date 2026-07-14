<?php

declare(strict_types=1);

namespace App\Domains\Courses\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Users\Models\User;

final class AcademicTermPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, AcademicTerm $term): bool
    {
        return $this->owns($user, $term);
    }

    public function update(User $user, AcademicTerm $term): bool
    {
        return $this->owns($user, $term);
    }

    public function delete(User $user, AcademicTerm $term): bool
    {
        return $this->owns($user, $term);
    }

    private function owns(User $user, AcademicTerm $term): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $term->user_id;
    }
}
