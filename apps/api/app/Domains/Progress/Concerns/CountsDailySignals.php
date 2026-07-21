<?php

declare(strict_types=1);

namespace App\Domains\Progress\Concerns;

use App\Domains\Planner\Models\FocusSession;
use App\Domains\Progress\Exceptions\ProgressPersistenceFailure;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Shared read primitives for the Progress domain.
 *
 * Every number produced here comes from a row the student actually created.
 * Nothing is derived, extrapolated, projected, or compared against another
 * period. See the product invariant documented on BuildProgressOverview.
 */
trait CountsDailySignals
{
    /**
     * Run a read aggregate at REPEATABLE READ READ ONLY, joining an outer
     * transaction when one is already open (the dashboard opens its own).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $work
     * @return TReturn
     */
    private function readAggregate(Closure $work, string $operation): mixed
    {
        $ownsTransaction = DB::transactionLevel() === 0;

        try {
            return DB::transaction(function () use ($work, $ownsTransaction) {
                if ($ownsTransaction) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }

                return $work();
            }, 3);
        } catch (QueryException $exception) {
            throw ProgressPersistenceFailure::fromQueryException($exception, $operation);
        }
    }

    /**
     * Bucket rows of one table into local calendar days by date_trunc.
     *
     * $table and $column are always internal literals chosen by the caller;
     * no request input reaches the raw fragment. The timezone is bound.
     *
     * @param  list<string>  $dates
     * @param  (Closure(Builder): void)|null  $constrain
     * @return array<string, int>
     */
    private function dailyCounts(
        string $table,
        string $column,
        int $userId,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $timezone,
        array $dates,
        ?Closure $constrain = null,
    ): array {
        $buckets = array_fill_keys($dates, 0);

        $query = DB::table($table)
            ->where('user_id', $userId)
            ->whereNotNull($column)
            ->where($column, '>=', $start)
            ->where($column, '<', $end);

        if ($constrain instanceof Closure) {
            $constrain($query);
        }

        $rows = $query
            ->selectRaw(
                sprintf(
                    "to_char(date_trunc('day', %s AT TIME ZONE CAST(? AS text)), 'YYYY-MM-DD') as local_day, count(*) as signal_total",
                    $column,
                ),
                [$timezone],
            )
            ->groupBy('local_day')
            ->get();

        foreach ($rows as $row) {
            $day = $row->local_day ?? null;
            if (is_string($day) && array_key_exists($day, $buckets)) {
                $buckets[$day] = (int) $row->signal_total;
            }
        }

        return $buckets;
    }

    /**
     * Focus minutes attributed to the local day the session starts on.
     *
     * @param  list<string>  $dates
     * @return array<string, int>
     */
    private function focusMinutesByDay(
        User $user,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $timezone,
        array $dates,
    ): array {
        $buckets = array_fill_keys($dates, 0);
        $sessions = FocusSession::query()
            ->where('user_id', $user->getKey())
            ->where('ends_at', '>', $start)
            ->where('starts_at', '<', $end)
            ->get(['starts_at', 'ends_at']);

        foreach ($sessions as $session) {
            $startsAt = $this->instant($session->getAttribute('starts_at'));
            $endsAt = $this->instant($session->getAttribute('ends_at'));

            if ($startsAt === null || $endsAt === null) {
                continue;
            }

            $day = $startsAt->setTimezone($timezone)->format('Y-m-d');

            if (array_key_exists($day, $buckets)) {
                $buckets[$day] += max(0, (int) round($startsAt->diffInMinutes($endsAt)));
            }
        }

        return $buckets;
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
