<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use RuntimeException;

final class UnsafeIntakeUrl extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct('The intake link is not safe to fetch: '.$reason);
    }
}
