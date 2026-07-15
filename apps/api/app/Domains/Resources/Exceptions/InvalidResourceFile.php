<?php

declare(strict_types=1);

namespace App\Domains\Resources\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class InvalidResourceFile extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_UNPROCESSABLE_ENTITY, 'The uploaded file is not supported.');
    }
}
