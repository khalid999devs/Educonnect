<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Enums;

enum KnowledgeLinkRelation: string
{
    case Related = 'related';
    case Supports = 'supports';
    case Contradicts = 'contradicts';
    case BuildsOn = 'builds_on';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $relation): string => $relation->value, self::cases());
    }
}
