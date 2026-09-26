<?php

declare(strict_types=1);

namespace App\Domains\Tools\Support;

use InvalidArgumentException;

final class ToolCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'name' => [
                'expression' => 'LOWER(tools.name)',
                'direction' => 'asc',
                'cursor_column' => 'tool_name_asc',
                'type' => 'name',
            ],
            '-last_reviewed_at' => [
                'expression' => 'tools.last_reviewed_at',
                'direction' => 'desc',
                'cursor_column' => 'tool_reviewed_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported tool sort.'),
        };
    }
}
