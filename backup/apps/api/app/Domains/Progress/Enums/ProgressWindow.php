<?php

declare(strict_types=1);

namespace App\Domains\Progress\Enums;

enum ProgressWindow: string
{
    case Week = 'week';
    case Month = 'month';
    case Term = 'term';

    /**
     * The hard ceiling on how many daily buckets a window may return.
     *
     * A term can legitimately run five months. Shipping 150 daily rows in one
     * payload violates the bounded-response rule, so a term window is clamped
     * to the most recent stretch and the response states the resolved dates
     * plainly rather than implying it covered the whole term.
     */
    public function maximumDays(): int
    {
        return match ($this) {
            self::Week => 7,
            self::Month => 31,
            self::Term => 92,
        };
    }
}
