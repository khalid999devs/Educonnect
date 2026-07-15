<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Tools\Concerns;

use App\Domains\Tools\Support\ToolCursorSort;
use App\Domains\Users\Models\User;
use Closure;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use InvalidArgumentException;
use LogicException;

trait HandlesToolInput
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

    protected function validateToolCursor(Validator $validator, string $sort): void
    {
        if ($this->input('cursor') === null) {
            return;
        }

        try {
            $definition = ToolCursorSort::for($sort);
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
        $sortValue = $values[$sortField] ?? null;
        $publicId = $values['public_id'] ?? null;

        if (array_keys($values) !== [$sortField, 'public_id', '_pointsToNextItems']
            || ! is_bool($values['_pointsToNextItems'] ?? null)
            || ! is_string($sortValue)
            || ! $this->validSortValue($sortValue, $definition['type'])
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

    private function validSortValue(string $value, string $type): bool
    {
        if ($type === 'name') {
            return $value !== ''
                && mb_strlen($value) <= 255
                && preg_match('/[\x00-\x1F\x7F]/u', $value) !== 1;
        }

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
}
