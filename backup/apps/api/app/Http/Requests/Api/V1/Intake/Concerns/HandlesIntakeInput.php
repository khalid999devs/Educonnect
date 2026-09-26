<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Intake\Concerns;

use App\Http\Requests\Api\V1\Guidance\Concerns\HandlesGuidanceInput;

trait HandlesIntakeInput
{
    use HandlesGuidanceInput;

    protected function nullableTrimmed(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
