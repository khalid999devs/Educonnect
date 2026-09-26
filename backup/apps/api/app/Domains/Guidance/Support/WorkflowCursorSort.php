<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Support;

use InvalidArgumentException;

final class WorkflowCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'title' => [
                'expression' => 'LOWER(workflow_recipes.title)',
                'direction' => 'asc',
                'cursor_column' => 'workflow_title_asc',
                'type' => 'name',
            ],
            '-last_reviewed_at' => [
                'expression' => 'workflow_recipes.last_reviewed_at',
                'direction' => 'desc',
                'cursor_column' => 'workflow_reviewed_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported workflow sort.'),
        };
    }
}
