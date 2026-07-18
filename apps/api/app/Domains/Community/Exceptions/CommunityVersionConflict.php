<?php

declare(strict_types=1);

namespace App\Domains\Community\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CommunityVersionConflict extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('The community record changed in another request.');
    }
}
