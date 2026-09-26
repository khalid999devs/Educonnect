<?php

declare(strict_types=1);

namespace App\Domains\Templates\Support;

use InvalidArgumentException;

final class TemplateCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'title' => [
                'expression' => 'LOWER(templates.title)',
                'direction' => 'asc',
                'cursor_column' => 'template_title_asc',
                'type' => 'name',
            ],
            '-last_reviewed_at' => [
                'expression' => 'templates.last_reviewed_at',
                'direction' => 'desc',
                'cursor_column' => 'template_reviewed_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported template sort.'),
        };
    }
}
