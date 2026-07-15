<?php

declare(strict_types=1);

namespace App\Domains\Resources\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Resources\Models\Resource;
use App\Domains\Users\Models\User;

final class ResourcePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, Resource $resource): bool
    {
        return $this->owns($user, $resource);
    }

    public function update(User $user, Resource $resource): bool
    {
        return $this->owns($user, $resource);
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $this->owns($user, $resource);
    }

    private function owns(User $user, Resource $resource): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $resource->user_id;
    }
}
