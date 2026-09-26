<?php

declare(strict_types=1);

namespace App\Domains\Courses\Support;

use InvalidArgumentException;

final class AcademicCursorSort
{
    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    public static function resolve(string $sort): array
    {
        return match ($sort) {
            'created_at' => self::definition('created_at', 'asc'),
            '-created_at' => self::definition('created_at', 'desc'),
            'updated_at' => self::definition('updated_at', 'asc'),
            '-updated_at' => self::definition('updated_at', 'desc'),
            default => throw new InvalidArgumentException('Unsupported academic cursor sort.'),
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
