<?php

declare(strict_types=1);

namespace App\Domains\Resources\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ResourceStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The resource cannot be changed in its current state.');
    }
}
