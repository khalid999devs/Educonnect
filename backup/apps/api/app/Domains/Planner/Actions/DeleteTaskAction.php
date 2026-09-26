<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerStateConflict;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class DeleteTaskAction
{
    public function __construct(private FindOwnedTask $tasks) {}

    public function execute(User $user, string $publicId, int $expectedVersion): void
    {
        try {
            DB::transaction(function () use ($user, $publicId, $expectedVersion): void {
                $task = $this->tasks->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('delete', $task);

                if ($task->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                if ($task->focusSessions()->exists()) {
                    throw new PlannerStateConflict;
                }

                $task->delete();
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.delete');
        }
    }
}
