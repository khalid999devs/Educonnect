<?php

declare(strict_types=1);

namespace App\Domains\Mentor\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class MentorStateConflict extends ConflictHttpException
{
    public function __construct(string $message = 'The mentor request is not in a state that allows this action.')
    {
        parent::__construct($message);
    }
}
