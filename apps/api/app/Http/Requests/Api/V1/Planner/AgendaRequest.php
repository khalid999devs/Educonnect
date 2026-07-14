<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner;

final class AgendaRequest extends PlannerWindowRequest
{
    protected function anchorKey(): string
    {
        return 'date';
    }

    protected function windowDays(): int
    {
        return 1;
    }
}
