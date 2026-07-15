<?php

declare(strict_types=1);

namespace App\Domains\Templates\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TemplateStateConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The template copy cannot be changed in its current state.');
    }
}
