<?php

declare(strict_types=1);

namespace App\Domains\Planner\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Data\PlannerWindowResult;
use App\Domains\Planner\Exceptions\PlannerPersistenceFailure;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ReadPlannerWindow
{
    public function execute(
        User $user,
        string $timezone,
        string $anchor,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        int $limit,
        string $operation,
    ): PlannerWindowResult {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use (
                $user,
                $timezone,
                $anchor,
                $startsAt,
                $endsAt,
                $limit,
                $ownsTransaction,
            ): PlannerWindowResult {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $taskQuery = Task::query()
                    ->where('user_id', $user->getKey())
                    ->whereNull('archived_at')
                    ->where('due_at', '>=', $startsAt)
                    ->where('due_at', '<', $endsAt);
                $focusQuery = FocusSession::query()
                    ->where('user_id', $user->getKey())
                    ->where('starts_at', '>', $startsAt->subHours(24))
                    ->where('starts_at', '<', $endsAt)
                    ->where('ends_at', '>', $startsAt);
                $counts = [
                    'tasks' => (clone $taskQuery)->count(),
                    'focus_sessions' => (clone $focusQuery)->count(),
                ];
                $tasks = $taskQuery
                    ->with('course')
                    ->orderBy('due_at')
                    ->orderBy('public_id')
                    ->limit($limit)
                    ->get();
                $focusSessions = $focusQuery
                    ->with(['task', 'course'])
                    ->orderBy('starts_at')
                    ->orderBy('public_id')
                    ->limit($limit)
                    ->get();
                $hasMore = [
                    'tasks' => $counts['tasks'] > $limit,
                    'focus_sessions' => $counts['focus_sessions'] > $limit,
                ];

                return new PlannerWindowResult(
                    timezone: $timezone,
                    anchor: $anchor,
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    tasks: $tasks,
                    focusSessions: $focusSessions,
                    counts: $counts,
                    hasMore: $hasMore,
                    limit: $limit,
                );
            }, 3);
        } catch (QueryException $exception) {
            $safeOperation = $operation === 'weekly' ? 'planner.weekly' : 'planner.agenda';

            throw PlannerPersistenceFailure::fromQueryException($exception, $safeOperation);
        }
    }
}
