<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Enums;

enum CollectionKind: string
{
    case Course = 'course';
    case Project = 'project';
    case Research = 'research';
    case Goal = 'goal';
    case General = 'general';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $kind): string => $kind->value, self::cases());
    }
}
