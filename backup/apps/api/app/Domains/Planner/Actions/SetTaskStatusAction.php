<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetTaskStatusAction
{
    public function __construct(private FindOwnedTask $tasks) {}

    public function execute(User $user, string $publicId, TaskStatus $status, int $expectedVersion): Task
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $status, $expectedVersion): Task {
                $task = $this->tasks->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $task);

                $currentStatus = $task->getAttribute('status');
                $currentStatusValue = $currentStatus instanceof TaskStatus ? $currentStatus->value : $currentStatus;

                if ($currentStatusValue === $status->value) {
                    return $task;
                }

                if ($task->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                $task->forceFill([
                    'status' => $status,
                    'completed_at' => $status === TaskStatus::Completed ? now() : null,
                    'version' => $task->version + 1,
                ])->save();

                return $task->refresh()->load('course');
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.status');
        }
    }
}
