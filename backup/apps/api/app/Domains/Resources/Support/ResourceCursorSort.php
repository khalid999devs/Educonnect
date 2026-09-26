<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use InvalidArgumentException;

final class ResourceCursorSort
{
    /**
     * Cursors carry only the sort column value, the public ULID, and a direction
     * flag. The value_type tells the request layer how to validate the opaque
     * cursor payload so a tampered cursor never reaches the database.
     *
     * @return array{column: string, direction: 'asc'|'desc', cursor_column: string, value_type: 'timestamp'|'text'}
     */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'updated_at' => [
                'column' => 'resources.updated_at',
                'direction' => 'asc',
                'cursor_column' => 'resource_updated_at_asc',
                'value_type' => 'timestamp',
            ],
            '-updated_at' => [
                'column' => 'resources.updated_at',
                'direction' => 'desc',
                'cursor_column' => 'resource_updated_at_desc',
                'value_type' => 'timestamp',
            ],
            'title' => [
                'column' => 'resources.title',
                'direction' => 'asc',
                'cursor_column' => 'resource_title_asc',
                'value_type' => 'text',
            ],
            '-title' => [
                'column' => 'resources.title',
                'direction' => 'desc',
                'cursor_column' => 'resource_title_desc',
                'value_type' => 'text',
            ],
            default => throw new InvalidArgumentException('Unsupported resource sort.'),
        };
    }
}
