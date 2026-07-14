<?php

declare(strict_types=1);

namespace App\Domains\Planner\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PlannerStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The planner record cannot be changed in its current state.');
    }
}
