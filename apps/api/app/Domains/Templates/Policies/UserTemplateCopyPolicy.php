<?php

declare(strict_types=1);

namespace App\Domains\Templates\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Templates\Models\UserTemplateCopy;
use App\Domains\Users\Models\User;

final class UserTemplateCopyPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, UserTemplateCopy $copy): bool
    {
        return $this->owns($user, $copy);
    }

    public function update(User $user, UserTemplateCopy $copy): bool
    {
        return $this->owns($user, $copy);
    }

    public function delete(User $user, UserTemplateCopy $copy): bool
    {
        return $this->owns($user, $copy);
    }

    private function owns(User $user, UserTemplateCopy $copy): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $copy->user_id;
    }
}
