<?php

declare(strict_types=1);

namespace App\Domains\Intake\Support;

use InvalidArgumentException;

final class IntakeCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            '-created_at' => [
                'expression' => 'intake_items.created_at',
                'direction' => 'desc',
                'cursor_column' => 'intake_created_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported intake sort.'),
        };
    }
}
