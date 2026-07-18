<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MentorVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The mentor record changed in another request.');
    }
}
