<?php

declare(strict_types=1);

namespace App\Domains\Intake\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class IntakeStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The intake item cannot be changed in its current state.');
    }
}
