<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\SecondBrain\Models\Collection;
use App\Domains\Users\Models\User;

final class CollectionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, Collection $collection): bool
    {
        return $this->owns($user, $collection);
    }

    public function update(User $user, Collection $collection): bool
    {
        return $this->owns($user, $collection);
    }

    public function delete(User $user, Collection $collection): bool
    {
        return $this->owns($user, $collection);
    }

    private function owns(User $user, Collection $collection): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $collection->user_id;
    }
}
