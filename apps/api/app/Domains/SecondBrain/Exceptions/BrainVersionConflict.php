<?php

declare(strict_types=1);

namespace App\Domains\SecondBrain\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class BrainVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The Second Brain record changed in another request.');
    }
}
