<?php

declare(strict_types=1);

namespace App\Domains\Auth\Exceptions;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Raised when a high-risk admin action is attempted without a fresh step-up
 * re-authentication in the current session. Renders as 423 Locked, which the
 * API renderer maps to the stable REAUTHENTICATION_REQUIRED code so the admin
 * SPA can prompt for a password confirmation and retry the original request.
 */
final class AdminReauthenticationRequired extends HttpException
{
    public function __construct()
    {
        parent::__construct(Response::HTTP_LOCKED, 'Re-authentication is required to continue.');
    }
}
