<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use RuntimeException;

final class InvalidSuggestionOutput extends RuntimeException
{
    public function __construct(string $reason)
    {
        parent::__construct('The classifier output failed schema validation: '.$reason);
    }
}
