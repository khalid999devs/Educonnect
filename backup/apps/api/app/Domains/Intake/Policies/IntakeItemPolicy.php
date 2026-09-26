<?php

declare(strict_types=1);

namespace App\Domains\Intake\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Users\Models\User;

final class IntakeItemPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, IntakeItem $item): bool
    {
        return $this->owns($user, $item);
    }

    public function update(User $user, IntakeItem $item): bool
    {
        return $this->owns($user, $item);
    }

    private function owns(User $user, IntakeItem $item): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $item->user_id;
    }
}
