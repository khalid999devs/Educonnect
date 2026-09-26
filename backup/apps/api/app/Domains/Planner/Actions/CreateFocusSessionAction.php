<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerStateConflict;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class CreateFocusSessionAction
{
    public function __construct(
        private FindOwnedTask $tasks,
        private FindOwnedCourse $courses,
    ) {}

    /** @param array{task_id: ?string, course_id: ?string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, note: ?string} $data */
    public function execute(User $user, array $data): FocusSession
    {
        Gate::forUser($user)->authorize('create', FocusSession::class);

        try {
            return DB::transaction(function () use ($user, $data): FocusSession {
                $task = $data['task_id'] === null
                    ? null
                    : $this->tasks->execute($user, $data['task_id'], lockForUpdate: true);
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);

                if ($task?->archived_at !== null || $course?->archived_at !== null) {
                    throw new PlannerStateConflict;
                }

                $session = new FocusSession;
                $session->forceFill([
                    'user_id' => $user->getKey(),
                    'task_id' => $task?->getKey(),
                    'course_id' => $course?->getKey(),
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'note' => $data['note'],
                    'version' => 1,
                ])->save();

                return $session->load(['task', 'course']);
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'focus.create');
        }
    }
}
