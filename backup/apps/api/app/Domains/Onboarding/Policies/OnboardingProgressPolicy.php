<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Onboarding\Models\OnboardingProgress;
use App\Domains\Users\Models\User;

final class OnboardingProgressPolicy
{
    public function view(User $user, OnboardingProgress $progress): bool
    {
        return $this->owns($user, $progress);
    }

    public function update(User $user, OnboardingProgress $progress): bool
    {
        return $this->owns($user, $progress);
    }

    public function complete(User $user, OnboardingProgress $progress): bool
    {
        return $this->owns($user, $progress);
    }

    private function owns(User $user, OnboardingProgress $progress): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $progress->user_id;
    }
}
