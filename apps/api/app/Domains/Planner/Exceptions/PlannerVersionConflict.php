<?php

declare(strict_types=1);

namespace App\Domains\Planner\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class PlannerVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The planner record changed in another request.');
    }
}
