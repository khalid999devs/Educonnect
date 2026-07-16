<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BrainStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The Second Brain record cannot be changed in its current state.');
    }
}
