<?php

declare(strict_types=1);

namespace App\Domains\Content\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ContentStateConflict extends ConflictHttpException
{
    public function __construct(string $message = 'This lifecycle change is not allowed from the current state.')
    {
        parent::__construct($message);
    }
}
