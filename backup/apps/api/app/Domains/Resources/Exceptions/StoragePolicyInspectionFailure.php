<?php

declare(strict_types=1);

namespace App\Domains\Resources\Exceptions;

use RuntimeException;

final class StoragePolicyInspectionFailure extends RuntimeException
{
    public function __construct(public readonly string $safeMessage)
    {
        parent::__construct($safeMessage);
    }
}
