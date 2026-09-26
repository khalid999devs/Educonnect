<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerStateConflict;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateTaskAction
{
    public function __construct(
        private FindOwnedTask $tasks,
        private FindOwnedCourse $courses,
    ) {}

    /** @param array{title: string, description: ?string, course_id: ?string, due_at: ?CarbonImmutable} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): Task
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): Task {
                $task = $this->tasks->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $task);
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);
                $desired = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'course_id' => $course?->getKey(),
                    'due_at' => $data['due_at']?->getTimestamp(),
                ];

                if ($this->canonical($task) === $desired) {
                    return $task;
                }

                if ($course?->archived_at !== null) {
                    throw new PlannerStateConflict;
                }

                if ($task->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                $task->forceFill([
                    'title' => $desired['title'],
                    'description' => $desired['description'],
                    'course_id' => $desired['course_id'],
                    'due_at' => $data['due_at'],
                    'version' => $task->version + 1,
                ])->save();

                return $task->refresh()->load('course');
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.update');
        }
    }

    /** @return array{title: string, description: ?string, course_id: mixed, due_at: ?int} */
    private function canonical(Task $task): array
    {
        $dueAt = $task->getAttribute('due_at');

        return [
            'title' => $task->title,
            'description' => $task->description,
            'course_id' => $task->course_id,
            'due_at' => $dueAt instanceof CarbonInterface ? $dueAt->getTimestamp() : null,
        ];
    }
}
