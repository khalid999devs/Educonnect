<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Courses\Concerns;

use App\Domains\Courses\Support\AcademicCursorSort;
use App\Domains\Users\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use LogicException;

trait HandlesAcademicInput
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

    protected function validateCursor(Validator $validator, string $sort): void
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
        $sortField = AcademicCursorSort::resolve($sort)['cursor_column'];
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
}
