<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use RuntimeException;

final class EmailAlreadyRegistered extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The canonical email address is already registered.');
    }
}
