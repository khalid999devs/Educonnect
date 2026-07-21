<?php

declare(strict_types=1);

namespace App\Domains\Study\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Study\Models\StudyArtifact;
use App\Domains\Users\Models\User;

/**
 * Study material is private to the student who generated it. Every check is
 * capability AND ownership: a privileged role grants no read of another
 * student's study artifacts.
 */
final class StudyArtifactPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, StudyArtifact $artifact): bool
    {
        return $this->owns($user, $artifact);
    }

    private function owns(User $user, StudyArtifact $artifact): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $artifact->user_id;
    }
}
