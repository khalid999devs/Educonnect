<?php

declare(strict_types=1);

namespace App\Domains\Planner\Actions;

use App\Domains\Courses\Queries\FindOwnedCourse;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Exceptions\PlannerStateConflict;
use App\Domains\Planner\Exceptions\PlannerVersionConflict;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Queries\FindOwnedFocusSession;
use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final readonly class UpdateFocusSessionAction
{
    public function __construct(
        private FindOwnedFocusSession $sessions,
        private FindOwnedTask $tasks,
        private FindOwnedCourse $courses,
    ) {}

    /** @param array{task_id: ?string, course_id: ?string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, note: ?string} $data */
    public function execute(User $user, string $publicId, array $data, int $expectedVersion): FocusSession
    {
        try {
            return DB::transaction(function () use ($user, $publicId, $data, $expectedVersion): FocusSession {
                $session = $this->sessions->execute($user, $publicId, lockForUpdate: true);
                Gate::forUser($user)->authorize('update', $session);
                $task = $data['task_id'] === null
                    ? null
                    : $this->tasks->execute($user, $data['task_id'], lockForUpdate: true);
                $course = $data['course_id'] === null
                    ? null
                    : $this->courses->execute($user, $data['course_id'], lockForUpdate: true);
                $desired = [
                    'task_id' => $task?->getKey(),
                    'course_id' => $course?->getKey(),
                    'starts_at' => $data['starts_at']->getTimestamp(),
                    'ends_at' => $data['ends_at']->getTimestamp(),
                    'note' => $data['note'],
                ];

                if ($this->canonical($session) === $desired) {
                    return $session;
                }

                if ($task?->archived_at !== null || $course?->archived_at !== null) {
                    throw new PlannerStateConflict;
                }

                if ($session->version !== $expectedVersion) {
                    throw new PlannerVersionConflict;
                }

                $session->forceFill([
                    'task_id' => $desired['task_id'],
                    'course_id' => $desired['course_id'],
                    'starts_at' => $data['starts_at'],
                    'ends_at' => $data['ends_at'],
                    'note' => $desired['note'],
                    'version' => $session->version + 1,
                ])->save();

                return $session->refresh()->load(['task', 'course']);
            }, 3);
        } catch (QueryException $exception) {
            throw PlannerPersistenceFailure::fromQueryException($exception, 'focus.update');
        }
    }

    /** @return array{task_id: mixed, course_id: mixed, starts_at: int, ends_at: int, note: ?string} */
    private function canonical(FocusSession $session): array
    {
        $startsAt = $session->getAttribute('starts_at');
        $endsAt = $session->getAttribute('ends_at');

        return [
            'task_id' => $session->task_id,
            'course_id' => $session->course_id,
            'starts_at' => $startsAt instanceof CarbonInterface ? $startsAt->getTimestamp() : 0,
            'ends_at' => $endsAt instanceof CarbonInterface ? $endsAt->getTimestamp() : 0,
            'note' => $session->note,
        ];
    }
}
