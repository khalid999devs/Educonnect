<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use App\Domains\Intake\Enums\IntakeFailureCode;
use RuntimeException;

final class IntakeAcquisitionFailure extends RuntimeException
{
    public function __construct(
        public readonly IntakeFailureCode $failureCode,
        string $detail,
    ) {
        parent::__construct($detail);
    }
}
