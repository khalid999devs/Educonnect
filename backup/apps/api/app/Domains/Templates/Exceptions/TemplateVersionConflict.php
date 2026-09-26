<?php

declare(strict_types=1);

namespace App\Domains\Templates\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class TemplateVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The template copy changed in another request.');
    }
}
