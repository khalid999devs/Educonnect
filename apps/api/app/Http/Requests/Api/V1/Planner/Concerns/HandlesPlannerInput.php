<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Planner\Concerns;

use App\Domains\Planner\Support\PlannerCursorSort;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LogicException;

trait HandlesPlannerInput
{
    public function authenticatedUser(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }

    /** @param list<string> $allowed */
    protected function rejectUnknownFields(Validator $validator, array $allowed): void
    {
        foreach (array_keys($this->all()) as $key) {
            if (! is_string($key) || ! in_array($key, $allowed, true)) {
                $validator->errors()->add((string) $key, 'This field is not allowed.');
            }
        }
    }

    protected function validateTaskCursor(Validator $validator, string $sort): void
    {
        if ($this->input('cursor') === null) {
            return;
        }

        try {
            $this->validateCursor($validator, PlannerCursorSort::task($sort));
        } catch (InvalidArgumentException) {
            // The sort allowlist reports its own validation error.
        }
    }

    protected function validateFocusCursor(Validator $validator, string $sort): void
    {
        if ($this->input('cursor') === null) {
            return;
        }

        try {
            $this->validateCursor($validator, PlannerCursorSort::focus($sort));
        } catch (InvalidArgumentException) {
            // The sort allowlist reports its own validation error.
        }
    }

    /** @param array{column: string, direction: 'asc'|'desc', cursor_column: string} $definition */
    private function validateCursor(Validator $validator, array $definition): void
    {
        $encoded = $this->input('cursor');

        if ($encoded === null) {
            return;
        }

        if (! is_string($encoded)) {
            $validator->errors()->add('cursor', 'The cursor is invalid.');

            return;
        }

        $cursor = Cursor::fromEncoded($encoded);

        if (! $cursor instanceof Cursor) {
            $validator->errors()->add('cursor', 'The cursor is invalid.');

            return;
        }

        $values = $cursor->toArray();
        $sortField = $definition['cursor_column'];
        $expectedKeys = [$sortField, 'public_id', '_pointsToNextItems'];
        $sortValue = $values[$sortField] ?? null;
        $publicId = $values['public_id'] ?? null;
        $parsedSortValue = is_string($sortValue)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $sortValue)
            : false;

        if (array_keys($values) !== $expectedKeys
            || ! is_bool($values['_pointsToNextItems'] ?? null)
            || ! is_string($sortValue)
            || ! $parsedSortValue instanceof DateTimeImmutable
            || $parsedSortValue->format('Y-m-d H:i:s') !== $sortValue
            || ! is_string($publicId)
            || preg_match('/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D', $publicId) !== 1) {
            $validator->errors()->add('cursor', 'The cursor does not match the requested sort.');
        }
    }

    protected function offsetDateTime(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value !== null && $this->parseOffsetDateTime($value) === null) {
                $fail("The {$attribute} field must be a second-precision RFC3339 timestamp with an offset.");
            }
        };
    }

    protected function localDate(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)
                || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) !== 1) {
                $fail("The {$attribute} field must use YYYY-MM-DD.");

                return;
            }

            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

            if (! $date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $value) {
                $fail("The {$attribute} field must be a valid calendar date.");
            }
        };
    }

    protected function ianaTimezone(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! in_array($value, DateTimeZone::listIdentifiers(), true)) {
                $fail("The {$attribute} field must be a supported IANA timezone.");
            }
        };
    }

    protected function plainSingleLineText(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if (preg_match('/[\x00-\x1F\x7F]/u', $value) === 1
                || str_contains($value, '<')
                || str_contains($value, '>')) {
                $fail("The {$attribute} field must be plain single-line text.");
            }
        };
    }

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

    protected function parsedDateTime(string $key): ?CarbonImmutable
    {
        return $this->parseOffsetDateTime($this->input($key));
    }

    private function parseOffsetDateTime(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)
            || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value) !== 1) {
            return null;
        }

        $normalized = str_ends_with($value, 'Z') ? substr($value, 0, -1).'+00:00' : $value;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $normalized);

        if (! $parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d\TH:i:sP') !== $normalized) {
            return null;
        }

        return CarbonImmutable::instance($parsed)->utc();
    }
}
