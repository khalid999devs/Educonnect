<?php

declare(strict_types=1);

namespace App\Domains\Auth\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

final readonly class MaxPasswordBytes implements ValidationRule
{
    public function __construct(private int $maximum) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->maximum) {
            $fail("The :attribute must not exceed {$this->maximum} bytes.");
        }
    }
}
