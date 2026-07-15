<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use InvalidArgumentException;

final class TemplateCopyCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'title' => [
                'expression' => 'LOWER(user_template_copies.title)',
                'direction' => 'asc',
                'cursor_column' => 'copy_title_asc',
                'type' => 'name',
            ],
            '-created_at' => [
                'expression' => 'user_template_copies.created_at',
                'direction' => 'desc',
                'cursor_column' => 'copy_created_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported template copy sort.'),
        };
    }
}
