<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Study\Concerns;

use App\Domains\Study\Support\StudyCursorSort;
use App\Domains\Users\Models\User;
use DateTimeImmutable;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\Validator;
use LogicException;

trait HandlesStudyInput
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

    /**
     * A cursor is only ever accepted when it matches the sort it was minted
     * for, so a tampered or replayed cursor is a 422 rather than a silent
     * re-scan of the table.
     */
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
        $sortField = StudyCursorSort::resolve($sort)['cursor_column'];
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
}
