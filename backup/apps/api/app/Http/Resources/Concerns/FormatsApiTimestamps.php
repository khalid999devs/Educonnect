<?php

declare(strict_types=1);

namespace App\Http\Resources\Concerns;

use Carbon\CarbonInterface;

trait FormatsApiTimestamps
{
    private function timestamp(mixed $value): ?string
    {
        return $value instanceof CarbonInterface ? $value->toISOString() : null;
    }
}
