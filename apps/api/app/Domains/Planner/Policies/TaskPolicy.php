<?php

declare(strict_types=1);

namespace App\Domains\Planner\Policies;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;

final class TaskPolicy
{
    public function create(User $user): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    private function owns(User $user, Task $task): bool
    {
        return $user->hasCapability(CapabilityKey::AcademicManageOwn)
            && (int) $user->getKey() === (int) $task->user_id;
    }
}
