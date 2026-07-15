<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Resources\Concerns;

use App\Domains\Resources\Support\ResourceCursorSort;
use App\Domains\Users\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LogicException;

trait HandlesResourceInput
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

    protected function validateResourceCursor(Validator $validator, string $sort): void
    {
        if ($this->input('cursor') === null) {
            return;
        }

        try {
            $definition = ResourceCursorSort::for($sort);
        } catch (InvalidArgumentException) {
            return;
        }

        $encoded = $this->input('cursor');

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

        if (array_keys($values) !== $expectedKeys
            || ! is_bool($values['_pointsToNextItems'] ?? null)
            || ! is_string($sortValue)
            || ! $this->validCursorTimestamp($sortValue)
            || ! is_string($publicId)
            || preg_match('/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D', $publicId) !== 1) {
            $validator->errors()->add('cursor', 'The cursor does not match the requested sort.');
        }
    }

    private function validCursorTimestamp(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) === 1) {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

            return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d H:i:s') === $value;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-]\d{2}(?::\d{2})?$/D', $value) !== 1) {
            return false;
        }

        $normalized = preg_match('/[+-]\d{2}$/D', $value) === 1 ? $value.':00' : $value;
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:sP', $normalized);

        return $parsed instanceof DateTimeImmutable
            && $parsed->format('Y-m-d H:i:sP') === $normalized;
    }

    protected function httpsUrl(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $parts = parse_url($value);

            if (preg_match('/[\x00-\x20\x7F]/u', $value) === 1
                || filter_var($value, FILTER_VALIDATE_URL) === false
                || ! is_array($parts)
                || ($parts['scheme'] ?? null) !== 'https'
                || ! is_string($parts['host'] ?? null)
                || $parts['host'] === ''
                || array_key_exists('user', $parts)
                || array_key_exists('pass', $parts)) {
                $fail("The {$attribute} field must be an HTTPS URL without credentials.");
            }
        };
    }

    protected function safeOriginalName(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            if ($value === '.'
                || $value === '..'
                || str_contains($value, '/')
                || str_contains($value, '\\')
                || preg_match('/[\x00-\x1F\x7F]/u', $value) === 1) {
                $fail("The {$attribute} field must be a safe filename without a path.");
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
}
