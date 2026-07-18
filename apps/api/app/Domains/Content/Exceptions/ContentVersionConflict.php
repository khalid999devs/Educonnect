<?php

declare(strict_types=1);

namespace App\Domains\Content\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ContentVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The content record changed in another request.');
    }
}
