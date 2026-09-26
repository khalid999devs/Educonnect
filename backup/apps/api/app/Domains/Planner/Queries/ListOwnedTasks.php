<?php

declare(strict_types=1);

namespace App\Domains\Planner\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Models\Task;
use App\Domains\Planner\Support\PlannerCursorSort;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;

final readonly class ListOwnedTasks
{
    public function __construct(private FindOwnedCourse $courses) {}

    /** @return CursorPaginator<int, Task> */
    public function execute(
        User $user,
        ?string $search,
        string $status,
        string $archiveStatus,
        ?string $coursePublicId,
        ?CarbonImmutable $dueFrom,
        ?CarbonImmutable $dueBefore,
        ?bool $hasDue,
        string $sort,
        int $perPage,
    ): CursorPaginator {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        try {
            $course = $coursePublicId === null ? null : $this->courses->execute($user, $coursePublicId);
            $sortDefinition = PlannerCursorSort::task($sort);
            $query = Task::query()
                ->select([
                    'tasks.*',
                    $sortDefinition['column'].' as '.$sortDefinition['cursor_column'],
                ])
                ->where('user_id', $user->getKey())
                ->with('course');

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            if ($archiveStatus === 'active') {
                $query->whereNull('archived_at');
            } elseif ($archiveStatus === 'archived') {
                $query->whereNotNull('archived_at');
            }

            if ($course !== null) {
                $query->where('course_id', $course->getKey());
            }

            if ($hasDue === true) {
                $query->whereNotNull('due_at');
            } elseif ($hasDue === false) {
                $query->whereNull('due_at');
            }

            if ($dueFrom !== null) {
                $query->where('due_at', '>=', $dueFrom);
            }

            if ($dueBefore !== null) {
                $query->where('due_at', '<', $dueBefore);
            }

            if ($search !== null) {
                $query->whereRaw("LOWER(title) LIKE ? ESCAPE '\\'", [$this->prefixPattern($search)]);
            }

            return $query
                ->orderBy($sortDefinition['cursor_column'], $sortDefinition['direction'])
                ->orderBy('public_id', $sortDefinition['direction'])
                ->cursorPaginate($perPage)
                ->withQueryString();
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'task.list');
        }
    }

    private function prefixPattern(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
    }
}
