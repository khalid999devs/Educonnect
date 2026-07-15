<?php

declare(strict_types=1);

namespace App\Domains\Resources\Support;

use InvalidArgumentException;

final class ResourceCursorSort
{
    /** @return array{column: string, direction: 'asc'|'desc', cursor_column: string} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'updated_at' => [
                'column' => 'resources.updated_at',
                'direction' => 'asc',
                'cursor_column' => 'resource_updated_at_asc',
            ],
            '-updated_at' => [
                'column' => 'resources.updated_at',
                'direction' => 'desc',
                'cursor_column' => 'resource_updated_at_desc',
            ],
            default => throw new InvalidArgumentException('Unsupported resource sort.'),
        };
    }
}
