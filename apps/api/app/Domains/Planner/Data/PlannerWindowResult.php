<?php

declare(strict_types=1);

namespace App\Domains\Planner\Data;

use App\Domains\Planner\Models\FocusSession;
use App\Domains\Planner\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class PlannerWindowResult
{
    /**
     * @param  Collection<int, Task>  $tasks
     * @param  Collection<int, FocusSession>  $focusSessions
     * @param  array{tasks: int, focus_sessions: int}  $counts
     * @param  array{tasks: bool, focus_sessions: bool}  $hasMore
     */
    public function __construct(
        public string $timezone,
        public string $anchor,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public Collection $tasks,
        public Collection $focusSessions,
        public array $counts,
        public array $hasMore,
        public int $limit,
    ) {}
}
