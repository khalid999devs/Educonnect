<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class SetTaskArchiveStateAction
{
    public function __construct(private FindOwnedTask $tasks) {}

    public function execute(User $user, string $publicId, bool $archived, int $expectedVersion): Task
    {
        $operation = $archived ? 'task.archive' : 'task.restore';

        try {
            return DB::transaction(function () use ($user, $publicId, $archived, $expectedVersion): Task {
                $task = $this->tasks->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $task);
                $alreadyDesired = $archived ? $task->archived_at !== null : $task->archived_at === null;

                if ($alreadyDesired) {
                    return $task;
                }

                if ($task->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                $task->forceFill([
                    'archived_at' => $archived ? now() : null,
                    'version' => $task->version + 1,
                ])->save();

                return $task->refresh()->load('course');
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, $operation);
        }
    }
}
