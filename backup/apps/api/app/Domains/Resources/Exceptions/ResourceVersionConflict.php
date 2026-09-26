<?php

declare(strict_types=1);

namespace App\Domains\Resources\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ResourceVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The resource changed in another request.');
    }
}
