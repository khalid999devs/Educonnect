<?php

declare(strict_types=1);

namespace App\Domains\Courses\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\Course;
use App\Domains\Users\Models\User;

final class CoursePolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function update(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->owns($user, $course);
    }

    private function owns(User $user, Course $course): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $course->user_id;
    }
}
