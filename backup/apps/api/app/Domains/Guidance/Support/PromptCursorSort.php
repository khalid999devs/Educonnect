<?php

declare(strict_types=1);

namespace App\Domains\Guidance\Support;

use InvalidArgumentException;

final class PromptCursorSort
{
    /** @return array{expression: string, direction: 'asc'|'desc', cursor_column: string, type: 'name'|'timestamp'} */
    public static function for(string $sort): array
    {
        return match ($sort) {
            'title' => [
                'expression' => 'LOWER(prompt_templates.title)',
                'direction' => 'asc',
                'cursor_column' => 'prompt_title_asc',
                'type' => 'name',
            ],
            '-last_reviewed_at' => [
                'expression' => 'prompt_templates.last_reviewed_at',
                'direction' => 'desc',
                'cursor_column' => 'prompt_reviewed_at_desc',
                'type' => 'timestamp',
            ],
            default => throw new InvalidArgumentException('Unsupported prompt sort.'),
        };
    }
}
