<?php

declare(strict_types=1);

namespace App\Domains\Progress\Queries;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Planner\Enums\TaskStatus;
use App\Domains\Progress\Concerns\CountsDailySignals;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The sidebar activity-rhythm strip: the last seven local days, each marked
 * active or not, with the real signals behind that mark.
 *
 * PRODUCT INVARIANT (README, doc 04, ADR-0018): this is a rhythm, not a game.
 * It reports which days had activity. It deliberately does NOT compute a
 * consecutive-day counter, a longest or best run, a flame, a badge, a
 * percentile, or any comparison against a previous period. A quiet week
 * renders as a quiet week and says so.
 */
final readonly class BuildActivityRhythm
{
    use CountsDailySignals;

    private const RHYTHM_DAYS = 7;

    /** @return array<string, mixed> */
    public function execute(User $user, string $timezone): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        return $this->readAggregate(
            fn (): array => $this->rhythm($user, $timezone),
            'progress.rhythm.read',
        );
    }

    /**
     * The compact dashboard projection of the same computation.
     *
     * The dashboard renders one focus-minute strip. It must never recompute
     * these figures itself: /progress is authoritative.
     *
     * @return array<string, mixed>
     */
    public function dashboardBlock(User $user, string $timezone): array
    {
        Gate::forUser($user)->authorize(CapabilityKey::AcademicManageOwn->value);

        /* Only the focus-minute signal is projected here, so only that signal
           is read. Computing the full rhythm and discarding three quarters of
           it would put three needless queries on the dashboard aggregate,
           which is query-budgeted by DashboardTest. */
        $focusMinutes = $this->readAggregate(
            function () use ($user, $timezone): array {
                $localNow = CarbonImmutable::now('UTC')->setTimezone($timezone);
                $windowEnd = $localNow->startOfDay()->addDay()->utc();
                $windowStart = $localNow->startOfDay()->subDays(self::RHYTHM_DAYS - 1)->utc();
                $dates = $this->localDates($windowStart, $timezone, self::RHYTHM_DAYS);

                $byDay = $this->focusMinutesByDay($user, $windowStart, $windowEnd, $timezone, $dates);

                return array_map(
                    static fn (string $date): array => [
                        'date' => $date,
                        'focus_minutes' => $byDay[$date],
                    ],
                    $dates,
                );
            },
            'progress.rhythm.read',
        );

        return [
            'has_activity' => array_sum(array_column($focusMinutes, 'focus_minutes')) > 0,
            'days' => $focusMinutes,
        ];
    }

    /** @return array<string, mixed> */
    private function rhythm(User $user, string $timezone): array
    {
        $localNow = CarbonImmutable::now('UTC')->setTimezone($timezone);
        $windowEnd = $localNow->startOfDay()->addDay()->utc();
        $windowStart = $localNow->startOfDay()->subDays(self::RHYTHM_DAYS - 1)->utc();
        $dates = $this->localDates($windowStart, $timezone, self::RHYTHM_DAYS);
        $userId = (int) $user->getKey();

        $tasks = $this->dailyCounts(
            'tasks',
            'completed_at',
            $userId,
            $windowStart,
            $windowEnd,
            $timezone,
            $dates,
            static function (Builder $query): void {
                $query->whereNull('archived_at')->where('status', TaskStatus::Completed->value);
            },
        );
        $focusMinutes = $this->focusMinutesByDay($user, $windowStart, $windowEnd, $timezone, $dates);
        $resources = $this->dailyCounts('resources', 'created_at', $userId, $windowStart, $windowEnd, $timezone, $dates);
        $notes = $this->dailyCounts('knowledge_notes', 'created_at', $userId, $windowStart, $windowEnd, $timezone, $dates);

        $days = [];
        foreach ($dates as $date) {
            $signals = [
                'tasks' => $tasks[$date],
                'focus_minutes' => $focusMinutes[$date],
                'resources' => $resources[$date],
                'notes' => $notes[$date],
            ];

            $days[] = [
                'date' => $date,
                'was_active' => array_sum($signals) > 0,
                'signals' => $signals,
            ];
        }

        return [
            'has_activity' => count(array_filter($days, static fn (array $day): bool => (bool) $day['was_active'])) > 0,
            'days' => $days,
        ];
    }
}
