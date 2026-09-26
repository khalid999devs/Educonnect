<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerStateConflict;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateTaskAction
{
    public function __construct(private FindOwnedCourse $courses) {}

    /** @param array{title: string, description: ?string, course_id: ?string, due_at: ?CarbonImmutable} $data */
    public function execute(User $user, array $data): Task
    {
        Gate::forUser($user)->authorize('create', Task::class);

        try {
            return DB::transaction(function () use ($user, $data): Task {
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);

                if ($course?->archived_at !== null) {
                    throw new PlannerStateConflict;
                }

                $task = new Task;
                $task->forceFill([
                    'user_id' => $user->getKey(),
                    'course_id' => $course?->getKey(),
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'due_at' => $data['due_at'],
                    'status' => TaskStatus::Pending,
                    'completed_at' => null,
                    'version' => 1,
                    'archived_at' => null,
                ])->save();

                return $task->load('course');
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.create');
        }
    }
}
