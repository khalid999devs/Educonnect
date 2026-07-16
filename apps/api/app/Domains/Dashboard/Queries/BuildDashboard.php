<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Courses\Models\Course;
use App\Domains\Dashboard\Exceptions\DashboardPersistenceFailure;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Onboarding\Models\UserProfile;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Domains\Tools\Enums\ToolPreferenceState;
use App\Domains\Tools\Enums\ToolReviewState;
use App\Domains\Tools\Models\Tool;
use App\Domains\Tools\Models\ToolCategory;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class BuildDashboard
{
    private const WHATS_NEXT_LIMIT = 5;

    private const TOOL_LIMIT = 4;

    private const TODAY_TASK_LIMIT = 3;

    private const KNOWLEDGE_LIMIT = 5;

    /** @return array<string, mixed> */
    public function execute(User $user, string $timezone): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($user, $timezone, $ownsTransaction): array {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                $now = CarbonImmutable::now('UTC');
                $localNow = $now->setTimezone($timezone);
                $todayStart = $localNow->startOfDay()->utc();
                $todayEnd = $localNow->startOfDay()->addDay()->utc();
                $weekStart = $localNow->startOfWeek(CarbonInterface::MONDAY)->utc();
                $weekEnd = $localNow->startOfWeek(CarbonInterface::MONDAY)->addWeek()->utc();

                return [
                    'timeframe' => [
                        'timezone' => $timezone,
                        'today' => $localNow->format('Y-m-d'),
                        'week_starts_on' => $weekStart->setTimezone($timezone)->format('Y-m-d'),
                        'week_ends_on' => $weekEnd->setTimezone($timezone)->subDay()->format('Y-m-d'),
                    ],
                    'cover' => $this->cover($user),
                    'quick_intake' => $this->quickIntake($user),
                    'whats_next' => $this->whatsNext($user, $now),
                    'tools' => $this->tools($user),
                    'today' => $this->today($user, $now, $todayStart, $todayEnd),
                    'second_brain' => $this->secondBrain($user),
                    'progress' => $this->progress($user, $timezone, $localNow, $weekStart, $weekEnd),
                    'personal_rhythm' => $this->personalRhythm($user, $timezone, $localNow),
                ];
            }, 3);
        } catch (QueryException $exception) {
            throw DashboardPersistenceFailure::fromQueryException($exception);
        }
    }

    /** @return array<string, mixed> */
    private function cover(User $user): array
    {
        $profile = UserProfile::query()->find($user->getKey());
        $today = CarbonImmutable::now('UTC')->format('Y-m-d');
        $term = AcademicTerm::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('starts_on')
            ->where('starts_on', '<=', $today)
            ->where(static function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today);
            })
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
        $activeCourseCount = Course::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->count();

        return [
            'name' => (string) $user->name,
            'institution' => $profile?->institution_name,
            'degree' => $profile?->degree,
            'major' => $profile?->major,
            'study_stage' => $profile?->year_label,
            'term' => $term instanceof AcademicTerm ? [
                'id' => (string) $term->public_id,
                'label' => (string) $term->label,
            ] : null,
            'active_course_count' => $activeCourseCount,
        ];
    }

    /** @return array<string, mixed> */
    private function quickIntake(User $user): array
    {
        $active = IntakeItem::query()
            ->where('user_id', $user->getKey())
            ->whereNotIn('state', ['saved', 'failed_final', 'cancelled'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
        $awaitingReview = IntakeItem::query()
            ->where('user_id', $user->getKey())
            ->where('state', 'awaiting_review')
            ->count();

        return [
            'active_item' => $active instanceof IntakeItem ? [
                'id' => (string) $active->public_id,
                'state' => $active->state->value,
                'failure_code' => $active->failure_code?->value,
                'submitted_at' => $this->timestamp($active->created_at),
            ] : null,
            'awaiting_review_count' => $awaitingReview,
        ];
    }

    /** @return array<string, mixed> */
    private function whatsNext(User $user, CarbonImmutable $now): array
    {
        $openTasks = Task::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('due_at');
        $overdueCount = (clone $openTasks)->where('due_at', '<', $now)->count();
        $upcomingCount = (clone $openTasks)->where('due_at', '>=', $now)->count();
        $tasks = $openTasks
            ->with('course')
            ->orderBy('due_at')
            ->orderBy('public_id')
            ->limit(self::WHATS_NEXT_LIMIT)
            ->get();

        return [
            'tasks' => $tasks->map(function (Task $task) use ($now): array {
                $status = $task->getAttribute('status');
                $dueAt = $this->instant($task->getAttribute('due_at'));
                $course = $task->getRelationValue('course');

                return [
                    'id' => (string) $task->public_id,
                    'title' => (string) $task->title,
                    'status' => $status instanceof TaskStatus ? $status->value : (string) $status,
                    'due_at' => $dueAt?->toISOString(),
                    'overdue' => $dueAt !== null && $dueAt->lessThan($now),
                    'course' => $course instanceof Course ? [
                        'id' => (string) $course->public_id,
                        'title' => (string) $course->title,
                    ] : null,
                ];
            })->values()->all(),
            'overdue_count' => $overdueCount,
            'upcoming_count' => $upcomingCount,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function tools(User $user): array
    {
        $tools = Tool::query()
            ->where('state', ToolReviewState::Published->value)
            ->whereNotExists(static function ($dismissed) use ($user): void {
                $dismissed->selectRaw('1')
                    ->from('user_tool_preferences')
                    ->whereColumn('user_tool_preferences.tool_id', 'tools.id')
                    ->where('user_tool_preferences.user_id', $user->getKey())
                    ->where('user_tool_preferences.state', ToolPreferenceState::Dismissed->value);
            })
            ->with('category')
            ->withExists(['preferences as saved' => static function ($saved) use ($user): void {
                $saved->where('user_id', $user->getKey())
                    ->where('state', ToolPreferenceState::Saved->value);
            }])
            ->orderByDesc('saved')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::TOOL_LIMIT)
            ->get();

        return $tools->map(static function (Tool $tool): array {
            $category = $tool->getRelationValue('category');

            return [
                'id' => (string) $tool->public_id,
                'name' => (string) $tool->name,
                'category' => $category instanceof ToolCategory ? (string) $category->name : null,
                'saved' => (bool) $tool->getAttribute('saved'),
            ];
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function today(
        User $user,
        CarbonImmutable $now,
        CarbonImmutable $todayStart,
        CarbonImmutable $todayEnd,
    ): array {
        $dueToday = Task::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->where('status', '!=', TaskStatus::Completed->value)
            ->where('due_at', '>=', $todayStart)
            ->where('due_at', '<', $todayEnd);
        $dueTodayCount = (clone $dueToday)->count();
        $dueTasks = $dueToday
            ->orderBy('due_at')
            ->orderBy('public_id')
            ->limit(self::TODAY_TASK_LIMIT)
            ->get();
        $nextFocusSession = FocusSession::query()
            ->where('user_id', $user->getKey())
            ->where('ends_at', '>', $now)
            ->where('starts_at', '<', $todayEnd)
            ->with(['task', 'course'])
            ->orderBy('starts_at')
            ->orderBy('public_id')
            ->first();

        return [
            'due_task_count' => $dueTodayCount,
            'due_tasks' => $dueTasks->map(fn (Task $task): array => [
                'id' => (string) $task->public_id,
                'title' => (string) $task->title,
                'due_at' => $this->timestamp($task->getAttribute('due_at')),
            ])->values()->all(),
            'next_focus_session' => $nextFocusSession instanceof FocusSession
                ? $this->focusSessionSummary($nextFocusSession, $now)
                : null,
        ];
    }

    /** @return array<string, mixed> */
    private function focusSessionSummary(FocusSession $session, CarbonImmutable $now): array
    {
        $startsAt = $this->instant($session->getAttribute('starts_at'));
        $task = $session->getRelationValue('task');
        $course = $session->getRelationValue('course');

        return [
            'id' => (string) $session->public_id,
            'starts_at' => $startsAt?->toISOString(),
            'ends_at' => $this->timestamp($session->getAttribute('ends_at')),
            'in_progress' => $startsAt !== null && $startsAt->lessThanOrEqualTo($now),
            'task_title' => $task instanceof Task ? (string) $task->title : null,
            'course_title' => $course instanceof Course ? (string) $course->title : null,
        ];
    }

    /** @return array<string, mixed> */
    private function secondBrain(User $user): array
    {
        $totalItems = KnowledgeItem::query()->where('user_id', $user->getKey())->count();
        $recent = KnowledgeItem::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::KNOWLEDGE_LIMIT)
            ->get();

        return [
            'total_item_count' => $totalItems,
            'recent_items' => $recent->map(fn (KnowledgeItem $item): array => [
                'id' => (string) $item->public_id,
                'title' => (string) $item->title,
                'source_type' => (string) $item->source_type,
                'updated_at' => $this->timestamp($item->getAttribute('updated_at')),
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function progress(
        User $user,
        string $timezone,
        CarbonImmutable $localNow,
        CarbonImmutable $weekStart,
        CarbonImmutable $weekEnd,
    ): array {
        $completedRows = Task::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->where('status', TaskStatus::Completed->value)
            ->where('completed_at', '>=', $weekStart)
            ->where('completed_at', '<', $weekEnd)
            ->get(['completed_at']);
        $dueThisWeek = Task::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->where('due_at', '>=', $weekStart)
            ->where('due_at', '<', $weekEnd)
            ->count();
        $focusMinutes = $this->focusMinutesBetween($user, $weekStart, $weekEnd);

        $dailyCompleted = array_fill_keys($this->localDates($weekStart, $timezone, 7), 0);
        foreach ($completedRows as $row) {
            $completedAt = $this->instant($row->getAttribute('completed_at'));
            if ($completedAt !== null) {
                $key = $completedAt->setTimezone($timezone)->format('Y-m-d');
                if (array_key_exists($key, $dailyCompleted)) {
                    $dailyCompleted[$key]++;
                }
            }
        }

        $completedCount = $completedRows->count();
        $nextAction = $this->nextAction($user);

        return [
            'timeframe' => [
                'timezone' => $timezone,
                'starts_on' => $weekStart->setTimezone($timezone)->format('Y-m-d'),
                'ends_on' => $weekEnd->setTimezone($timezone)->subDay()->format('Y-m-d'),
            ],
            'completed_task_count' => $completedCount,
            'due_task_count' => $dueThisWeek,
            'focus_minutes' => $focusMinutes,
            'daily_completed' => array_map(
                static fn (string $date, int $count): array => ['date' => $date, 'completed' => $count],
                array_keys($dailyCompleted),
                array_values($dailyCompleted),
            ),
            'summary' => $this->summary($completedCount, $dueThisWeek, $focusMinutes),
            'next_action' => $nextAction,
        ];
    }

    /** @return array<string, mixed> */
    private function personalRhythm(User $user, string $timezone, CarbonImmutable $localNow): array
    {
        $windowEnd = $localNow->startOfDay()->addDay()->utc();
        $windowStart = $localNow->startOfDay()->subDays(6)->utc();
        $sessions = FocusSession::query()
            ->where('user_id', $user->getKey())
            ->where('ends_at', '>', $windowStart)
            ->where('starts_at', '<', $windowEnd)
            ->get(['starts_at', 'ends_at']);

        $days = array_fill_keys($this->localDates($windowStart, $timezone, 7), 0);
        foreach ($sessions as $session) {
            $startsAt = $this->instant($session->getAttribute('starts_at'));
            $endsAt = $this->instant($session->getAttribute('ends_at'));
            if ($startsAt === null || $endsAt === null) {
                continue;
            }

            $key = $startsAt->setTimezone($timezone)->format('Y-m-d');
            if (array_key_exists($key, $days)) {
                $days[$key] += max(0, (int) round($startsAt->diffInMinutes($endsAt)));
            }
        }

        return [
            'has_activity' => array_sum($days) > 0,
            'days' => array_map(
                static fn (string $date, int $minutes): array => ['date' => $date, 'focus_minutes' => $minutes],
                array_keys($days),
                array_values($days),
            ),
        ];
    }

    /** @return array<string, mixed>|null */
    private function nextAction(User $user): ?array
    {
        $task = Task::query()
            ->where('user_id', $user->getKey())
            ->whereNull('archived_at')
            ->where('status', '!=', TaskStatus::Completed->value)
            ->whereNotNull('due_at')
            ->orderBy('due_at')
            ->orderBy('public_id')
            ->first();

        if ($task instanceof Task) {
            return [
                'kind' => 'task',
                'id' => (string) $task->public_id,
                'title' => (string) $task->title,
                'due_at' => $this->timestamp($task->getAttribute('due_at')),
            ];
        }

        $reviewItem = IntakeItem::query()
            ->where('user_id', $user->getKey())
            ->where('state', 'awaiting_review')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        if ($reviewItem instanceof IntakeItem) {
            return [
                'kind' => 'intake_review',
                'id' => (string) $reviewItem->public_id,
                'title' => 'Review your Smart Intake suggestions',
                'due_at' => null,
            ];
        }

        return null;
    }

    private function focusMinutesBetween(User $user, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $sessions = FocusSession::query()
            ->where('user_id', $user->getKey())
            ->where('ends_at', '>', $start)
            ->where('starts_at', '<', $end)
            ->get(['starts_at', 'ends_at']);
        $minutes = 0;

        foreach ($sessions as $session) {
            $startsAt = $this->instant($session->getAttribute('starts_at'));
            $endsAt = $this->instant($session->getAttribute('ends_at'));
            if ($startsAt !== null && $endsAt !== null) {
                $minutes += max(0, (int) round($startsAt->diffInMinutes($endsAt)));
            }
        }

        return $minutes;
    }

    private function summary(int $completed, int $due, int $focusMinutes): string
    {
        if ($completed === 0 && $due === 0 && $focusMinutes === 0) {
            return 'No planner activity recorded this week yet.';
        }

        $parts = [];
        $parts[] = $due > 0
            ? sprintf('You completed %d of %d tasks due this week.', $completed, $due)
            : sprintf('You completed %d %s this week.', $completed, $completed === 1 ? 'task' : 'tasks');

        if ($focusMinutes > 0) {
            $parts[] = sprintf('You logged %d focus minutes.', $focusMinutes);
        }

        return implode(' ', $parts);
    }

    /** @return list<string> */
    private function localDates(CarbonImmutable $utcStart, string $timezone, int $days): array
    {
        $cursor = $utcStart->setTimezone($timezone)->startOfDay();
        $dates = [];

        for ($index = 0; $index < $days; $index++) {
            $dates[] = $cursor->addDays($index)->format('Y-m-d');
        }

        return $dates;
    }

    private function instant(mixed $value): ?CarbonImmutable
    {
        return $value instanceof CarbonInterface ? CarbonImmutable::instance($value) : null;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
