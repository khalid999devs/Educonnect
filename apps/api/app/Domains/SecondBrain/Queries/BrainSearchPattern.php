<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Queries;

final class BrainSearchPattern
{
    public static function prefix(string $search): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search)).'%';
    }
}
