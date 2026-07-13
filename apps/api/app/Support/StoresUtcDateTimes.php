<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

trait StoresUtcDateTimes
{
    /**
     * @param  DateTimeInterface|int|string|null  $value
     */
    public function fromDateTime($value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return $this->asDateTime($value)
            ->utc()
            ->format($this->getDateFormat());
    }
}
