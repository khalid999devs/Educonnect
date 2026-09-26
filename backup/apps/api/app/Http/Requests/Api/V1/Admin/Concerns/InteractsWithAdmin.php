<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Concerns;

use App\Domains\Users\Models\User;
use App\Support\RequestId;
use Closure;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Shared input handling for admin-surface Form Requests. The admin app
 * authenticates on the `admin` guard, so the authenticated actor is always
 * resolved through it. All admin lists paginate newest-first on `created_at`
 * with a `public_id` tiebreaker, so one cursor validator serves them all.
 */
trait InteractsWithAdmin
{
    public function adminUser(): User
    {
        $user = $this->user('admin');

        if (! $user instanceof User) {
            throw new LogicException('An authenticated administrator is required.');
        }

        return $user;
    }

    public function requestId(): string
    {
        return RequestId::getOrCreate($this);
    }

    public function perPage(): int
    {
        return (int) $this->input('per_page', 20);
    }

    /**
     * Every sensitive admin action records an immutable reason (doc 08). The
     * bounds mirror the audit store's own constraint (1 - 2000 characters); the
     * text is plain (no markup or control characters).
     *
     * @return list<mixed>
     */
    protected function reasonRules(): array
    {
        return ['required', 'string', 'min:1', 'max:2000', $this->plainMultilineText()];
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

    public function reason(): string
    {
        $value = $this->input('reason');

        return is_string($value) ? trim($value) : '';
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

    /** Validates the opaque cursor decodes to the shared created-at/public_id shape. */
    protected function validateCreatedAtCursor(Validator $validator): void
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
        $sortValue = $values['cursor_created_at_desc'] ?? null;
        $publicId = $values['public_id'] ?? null;
        $parsedSortValue = is_string($sortValue)
            ? DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $sortValue)
            : false;

        if (array_keys($values) !== ['cursor_created_at_desc', 'public_id', '_pointsToNextItems']
            || ! is_bool($values['_pointsToNextItems'] ?? null)
            || ! is_string($sortValue)
            || ! $parsedSortValue instanceof DateTimeImmutable
            || $parsedSortValue->format('Y-m-d H:i:s') !== $sortValue
            || ! is_string($publicId)
            || preg_match('/^[01234567][0-9abcdefghjkmnpqrstvwxyz]{25}$/D', $publicId) !== 1) {
            $validator->errors()->add('cursor', 'The cursor does not match the requested sort.');
        }
    }
}
