<?php

declare(strict_types=1);

namespace App\Domains\Study\Support;

use InvalidArgumentException;

/**
 * The approved cursor sorts for study artifact lists. The aliased cursor column
 * is cast on the model; without that cast the raw timestamptz reaches the
 * cursor as "Y-m-d H:i:s+00" and the next page 422s.
 */
final class StudyCursorSort
{
    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    public static function resolve(string $sort): array
    {
        return match ($sort) {
            'created_at' => self::definition('created_at', 'asc'),
            '-created_at' => self::definition('created_at', 'desc'),
            'updated_at' => self::definition('updated_at', 'asc'),
            '-updated_at' => self::definition('updated_at', 'desc'),
            default => throw new InvalidArgumentException('Unsupported study cursor sort.'),
        };
    }

    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    private static function definition(string $column, string $direction): array
    {
        return [
            'column' => $column,
            'direction' => $direction,
            'cursor_column' => "cursor_{$column}_{$direction}",
        ];
    }
}
