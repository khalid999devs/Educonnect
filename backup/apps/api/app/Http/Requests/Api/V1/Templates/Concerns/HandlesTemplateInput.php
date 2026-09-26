<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Templates\Concerns;

use App\Http\Requests\Api\V1\Guidance\Concerns\HandlesGuidanceInput;
use Closure;

trait HandlesTemplateInput
{
    use HandlesGuidanceInput;

    protected function plainMultilineText(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value) === 1
                || str_contains($value, '<')
                || str_contains($value, '>')) {
                $fail("The {$attribute} field must be plain text.");
            }
        };
    }

    protected function nullableTrimmed(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
