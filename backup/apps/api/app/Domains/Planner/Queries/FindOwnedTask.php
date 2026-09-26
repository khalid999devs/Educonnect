<?php

declare(strict_types=1);

namespace App\Domains\Planner\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindOwnedTask
{
    public function execute(User $user, string $publicId, bool $lockForUpdate = false): Task
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $query = Task::query()
                ->where('user_id', $user->getKey())
                ->where('public_id', $publicId)
                ->with('course');

            if ($lockForUpdate) {
                $query->lockForUpdate();
            }

            $task = $query->first();

            if (! $task instanceof Task) {
                throw new NotFoundHttpException;
            }

            Gate::forUser($user)->authorize('view', $task);

            return $task;
        } catch (QueryException $exception) {
            if ($lockForUpdate) {
                throw $exception;
            }

            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.read');
        }
    }
}
