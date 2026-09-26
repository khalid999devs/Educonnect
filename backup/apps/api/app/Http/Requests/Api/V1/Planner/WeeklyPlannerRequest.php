<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

final class WeeklyPlannerRequest extends PlannerWindowRequest
{
    protected function anchorKey(): string
    {
        return 'week_start';
    }

    protected function windowDays(): int
    {
        return 7;
    }
}
