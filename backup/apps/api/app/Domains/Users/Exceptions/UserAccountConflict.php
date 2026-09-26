<?php

declare(strict_types=1);

namespace App\Domains\Users\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class UserAccountConflict extends ConflictHttpException
{
    public function __construct(string $message = 'The account is not in a state that allows this action.')
    {
        parent::__construct($message);
    }
}
