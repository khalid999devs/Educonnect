<?php

declare(strict_types=1);

namespace App\Domains\Planner\Support;

use InvalidArgumentException;

final class PlannerCursorSort
{
    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    public static function task(string $sort): array
    {
        return self::resolve($sort, ['updated_at']);
    }

    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    public static function focus(string $sort): array
    {
        return self::resolve($sort, ['starts_at']);
    }

    /**
     * @param  list<string>  $allowed
     * @return array{column: string, direction: 'asc'|'desc', cursor_column: string}
     */
    private static function resolve(string $sort, array $allowed): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $allowed, true)) {
            throw new InvalidArgumentException('Unsupported planner cursor sort.');
        }

        return [
            'column' => $column,
            'direction' => $direction,
            'cursor_column' => "cursor_{$column}_{$direction}",
        ];
    }
}
