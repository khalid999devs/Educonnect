<?php

declare(strict_types=1);

namespace App\Domains\Planner\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Support\PlannerCursorSort;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

final readonly class ListOwnedFocusSessions
{
    public function __construct(
        private FindOwnedTask $tasks,
        private FindOwnedCourse $courses,
    ) {}

    /** @return CursorPaginator<int, FocusSession> */
    public function execute(
        User $user,
        ?string $taskPublicId,
        ?string $coursePublicId,
        ?CarbonImmutable $overlapFrom,
        ?CarbonImmutable $overlapBefore,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $task = $taskPublicId === null ? null : $this->tasks->execute($user, $taskPublicId);
            $course = $coursePublicId === null ? null : $this->courses->execute($user, $coursePublicId);
            $sortDefinition = PlannerCursorSort::focus($sort);
            $query = FocusSession::query()
                ->select([
                    'focus_sessions.*',
                    $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                ])
                ->where('user_id', $user->getKey())
                ->with(['task', 'course']);

            if ($task !== null) {
                $query->where('task_id', $task->getKey());
            }

            if ($course !== null) {
                $query->where('course_id', $course->getKey());
            }

            if ($overlapFrom !== null) {
                $query->where('starts_at', '>', $overlapFrom->subHours(24))
                    ->where('ends_at', '>', $overlapFrom);
            }

            if ($overlapBefore !== null) {
                $query->where('starts_at', '<', $overlapBefore);
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'focus.list');
        }
    }
}
