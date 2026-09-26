<?php

declare(strict_types=1);

namespace App\Domains\Planner\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Users\Models\User;

final class FocusSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, FocusSession $session): bool
    {
        return $this->owns($user, $session);
    }

    public function update(User $user, FocusSession $session): bool
    {
        return $this->owns($user, $session);
    }

    public function delete(User $user, FocusSession $session): bool
    {
        return $this->owns($user, $session);
    }

    private function owns(User $user, FocusSession $session): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $session->user_id;
    }
}
