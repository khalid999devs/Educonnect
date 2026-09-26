<?php

declare(strict_types=1);

namespace App\Domains\Progress\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Courses\Models\AcademicTerm;
use App\Domains\Intake\Enums\IntakeState;
use App\Domains\Intake\Models\IntakeItem;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Planner\Models\Task;
use App\Domains\Progress\Concerns\CountsDailySignals;
use App\Domains\Progress\Enums\ProgressWindow;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The single authoritative progress read model.
 *
 * PRODUCT INVARIANT (README, docs/educonnect/04, ADR-0018): all progress is
 * computed from the student's own real records at read time. There are no
 * streaks, no consecutive-day counters, no best-run records, no flames, no
 * badges, no percentiles, no league positions, and no "vs last week" deltas.
 * An empty window honestly says it is empty. Adding any derived motivational
 * metric here is a product regression, not a feature. ProgressTest enforces
 * this and will fail on the vocabulary alone.
 *
 * This class is the ONLY place these figures are computed. The dashboard's
 * compact block delegates to dashboardBlock() rather than keeping a copy.
 */
final readonly class BuildProgressOverview
{
    use CountsDailySignals;

    private const DASHBOARD_DAYS = 7;

    public function __construct(private BuildActivityRhythm $activityRhythm) {}

    /** @return array<string, mixed> */
    public function execute(User $user, string $timezone, ProgressWindow $window): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        $overview = $this->readAggregate(
            fn (): array => $this->overview($user, $timezone, $window),
            'progress.overview.read',
        );

        $overview['activity_rhythm'] = $this->activityRhythm->execute($user, $timezone);

        return $overview;
    }

    /**
     * The compact dashboard projection, in the exact shape the dashboard
     * contract already publishes. Same computation, narrower output.
     *
     * @return array<string, mixed>
     */
    public function dashboardBlock(User $user, string $timezone): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        return $this->readAggregate(function () use ($user, $timezone): array {
            $localNow = CarbonImmutable::now('UTC')->setTimezone($timezone);
            $start = $localNow->startOfWeek(CarbonInterface::MONDAY)->utc();
            $end = $localNow->startOfWeek(CarbonInterface::MONDAY)->addWeek()->utc();
            $dates = $this->localDates($start, $timezone, self::DASHBOARD_DAYS);
            $userId = (int) $user->getKey();

            $completedByDay = $this->completedTasksByDay($userId, $start, $end, $timezone, $dates);
            $completed = array_sum($completedByDay);
            $due = $this->dueTaskCount($userId, $start, $end);
            $focusMinutes = array_sum($this->focusMinutesByDay($user, $start, $end, $timezone, $dates));

            return [
                'timeframe' => [
                    'timezone' => $timezone,
                    'starts_on' => $start->setTimezone($timezone)->format('Y-m-d'),
                    'ends_on' => $end->setTimezone($timezone)->subDay()->format('Y-m-d'),
                ],
                'completed_task_count' => $completed,
                'due_task_count' => $due,
                'focus_minutes' => $focusMinutes,
                'daily_completed' => array_map(
                    static fn (string $date, int $count): array => ['date' => $date, 'completed' => $count],
                    array_keys($completedByDay),
                    array_values($completedByDay),
                ),
                'summary' => $this->plannerSummary($completed, $due, $focusMinutes),
                'next_action' => $this->nextAction($user),
            ];
        }, 'progress.overview.read');
    }

    /** @return array<string, mixed> */
    private function overview(User $user, string $timezone, ProgressWindow $window): array
    {
        $localNow = CarbonImmutable::now('UTC')->setTimezone($timezone);
        [$start, $end, $termLabel] = $this->resolveWindow($user, $timezone, $localNow, $window);
        $dayCount = $this->dayCount($start, $end, $timezone);
        $dates = $this->localDates($start, $timezone, $dayCount);
        $userId = (int) $user->getKey();

        $tasksCompleted = $this->completedTasksByDay($userId, $start, $end, $timezone, $dates);
        $focusMinutes = $this->focusMinutesByDay($user, $start, $end, $timezone, $dates);
        $resourcesAdded = $this->dailyCounts('resources', 'created_at', $userId, $start, $end, $timezone, $dates);
        $notesWritten = $this->dailyCounts('knowledge_notes', 'created_at', $userId, $start, $end, $timezone, $dates);
        $knowledgeItems = $this->dailyCounts('knowledge_items', 'created_at', $userId, $start, $end, $timezone, $dates);
        $templateCopies = $this->dailyCounts('user_template_copies', 'created_at', $userId, $start, $end, $timezone, $dates);
        $researchSources = $this->dailyCounts('research_topic_sources', 'updated_at', $userId, $start, $end, $timezone, $dates);
        $intakeProcessed = $this->dailyCounts(
            'intake_items',
            'finished_at',
            $userId,
            $start,
            $end,
            $timezone,
            $dates,
            static function (Builder $query): void {
                $query->where('state', IntakeState::Saved->value);
            },
        );

        $totals = [
            'tasks_completed' => array_sum($tasksCompleted),
            'tasks_due' => $this->dueTaskCount($userId, $start, $end),
            'focus_minutes' => array_sum($focusMinutes),
            'resources_added' => array_sum($resourcesAdded),
            'intake_items_processed' => array_sum($intakeProcessed),
            'knowledge_items_added' => array_sum($knowledgeItems),
            'notes_written' => array_sum($notesWritten),
            'template_copies_created' => array_sum($templateCopies),
            'research_sources_reviewed' => array_sum($researchSources),
        ];

        $daily = [];
        foreach ($dates as $date) {
            $daily[] = [
                'date' => $date,
                'tasks_completed' => $tasksCompleted[$date],
                'focus_minutes' => $focusMinutes[$date],
                'resources_added' => $resourcesAdded[$date],
                'notes_written' => $notesWritten[$date],
            ];
        }

        $recordedTotal = $totals['tasks_completed']
            + $totals['focus_minutes']
            + $totals['resources_added']
            + $totals['intake_items_processed']
            + $totals['knowledge_items_added']
            + $totals['notes_written']
            + $totals['template_copies_created']
            + $totals['research_sources_reviewed'];

        return [
            'timeframe' => [
                'timezone' => $timezone,
                'window' => $window->value,
                'starts_on' => $start->setTimezone($timezone)->format('Y-m-d'),
                'ends_on' => $end->setTimezone($timezone)->subDay()->format('Y-m-d'),
                'term_label' => $termLabel,
            ],
            'has_activity' => $recordedTotal > 0,
            'totals' => $totals,
            'daily' => $daily,
            'summary' => $this->overviewSummary($totals, $window),
            'next_action' => $this->nextAction($user),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string|null}
     */
    private function resolveWindow(
        User $user,
        string $timezone,
        CarbonImmutable $localNow,
        ProgressWindow $window,
    ): array {
        $end = $localNow->startOfDay()->addDay();

        if ($window === ProgressWindow::Week) {
            $start = $localNow->startOfWeek(CarbonInterface::MONDAY);

            return [$start->utc(), $start->addWeek()->utc(), null];
        }

        if ($window === ProgressWindow::Month) {
            return [$localNow->startOfMonth()->utc(), $end->utc(), null];
        }

        $term = $this->activeTerm($user, $localNow);
        $earliest = $end->subDays($window->maximumDays());
        $termStart = $term instanceof AcademicTerm
            ? $this->termStart($term, $timezone)
            : null;
        $start = $termStart instanceof CarbonImmutable && $termStart->greaterThan($earliest)
            ? $termStart
            : $earliest;

        return [
            $start->utc(),
            $end->utc(),
            $term instanceof AcademicTerm ? (string) $term->label : null,
        ];
    }

    private function activeTerm(User $user, CarbonImmutable $localNow): ?AcademicTerm
    {
        $today = $localNow->format('Y-m-d');

        return AcademicTerm::query()
            ->where('user_id', $user->getKey())
            ->whereNotNull('starts_on')
            ->where('starts_on', '<=', $today)
            ->where(static function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhere('ends_on', '>=', $today);
            })
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    private function termStart(AcademicTerm $term, string $timezone): ?CarbonImmutable
    {
        $startsOn = $term->getAttribute('starts_on');

        if ($startsOn instanceof CarbonInterface) {
            return CarbonImmutable::instance($startsOn)->setTimezone($timezone)->startOfDay();
        }

        if (is_string($startsOn) && $startsOn !== '') {
            return CarbonImmutable::parse($startsOn, $timezone)->startOfDay();
        }

        return null;
    }

    private function dayCount(CarbonImmutable $start, CarbonImmutable $end, string $timezone): int
    {
        $localStart = $start->setTimezone($timezone)->startOfDay();
        $localEnd = $end->setTimezone($timezone)->startOfDay();

        return max(1, (int) round($localStart->diffInDays($localEnd)));
    }

    /**
     * @param  list<string>  $dates
     * @return array<string, int>
     */
    private function completedTasksByDay(
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $timezone,
        array $dates,
    ): array {
        return $this->dailyCounts(
            'tasks',
            'completed_at',
            $userId,
            $start,
            $end,
            $timezone,
            $dates,
            static function (Builder $query): void {
                $query->whereNull('archived_at')->where('status', TaskStatus::Completed->value);
            },
        );
    }

    private function dueTaskCount(int $userId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return Task::query()
            ->where('user_id', $userId)
            ->whereNull('archived_at')
            ->where('due_at', '>=', $start)
            ->where('due_at', '<', $end)
            ->count();
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
            ->where('state', IntakeState::AwaitingReview->value)
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

    private function plannerSummary(int $completed, int $due, int $focusMinutes): string
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

    /**
     * A plain description of what was recorded. No comparison to any other
     * period, no encouragement, no score.
     *
     * @param  array<string, int>  $totals
     */
    private function overviewSummary(array $totals, ProgressWindow $window): string
    {
        $label = match ($window) {
            ProgressWindow::Week => 'this week',
            ProgressWindow::Month => 'this month',
            ProgressWindow::Term => 'in this window',
        };

        $parts = [];

        if ($totals['tasks_completed'] > 0) {
            $parts[] = sprintf(
                '%d %s completed',
                $totals['tasks_completed'],
                $totals['tasks_completed'] === 1 ? 'task' : 'tasks',
            );
        }

        if ($totals['focus_minutes'] > 0) {
            $parts[] = sprintf('%d focus minutes logged', $totals['focus_minutes']);
        }

        if ($totals['resources_added'] > 0) {
            $parts[] = sprintf(
                '%d %s added',
                $totals['resources_added'],
                $totals['resources_added'] === 1 ? 'resource' : 'resources',
            );
        }

        if ($totals['notes_written'] > 0) {
            $parts[] = sprintf(
                '%d %s written',
                $totals['notes_written'],
                $totals['notes_written'] === 1 ? 'note' : 'notes',
            );
        }

        if ($parts === []) {
            return sprintf('No activity recorded %s yet.', $label);
        }

        return sprintf('You have %s %s.', $this->joinParts($parts), $label);
    }

    /** @param list<string> $parts */
    private function joinParts(array $parts): string
    {
        if (count($parts) === 1) {
            return $parts[0];
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }
}
