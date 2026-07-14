<?php

declare(strict_types=1);

namespace App\Domains\Courses\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class AcademicStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The academic record cannot be changed in its current state.');
    }
}
