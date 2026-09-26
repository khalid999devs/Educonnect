<?php

declare(strict_types=1);

namespace App\Domains\Community\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CommunityStateConflict extends ConflictHttpException
{
    public function __construct(string $message = 'The community record is not in a state that allows this action.')
    {
        parent::__construct($message);
    }
}
