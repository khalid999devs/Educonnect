<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use App\Support\Ai\Exceptions\InvalidAiOutput;

final class InvalidPurposeOutput extends InvalidAiOutput
{
    public function __construct(string $reason)
    {
        parent::__construct('The purpose router output failed schema validation: '.$reason);
    }
}
