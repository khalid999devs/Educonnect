<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use App\Support\Ai\Exceptions\InvalidAiOutput;

final class InvalidSuggestionOutput extends InvalidAiOutput
{
    public function __construct(string $reason)
    {
        parent::__construct('The classifier output failed schema validation: '.$reason);
    }
}
