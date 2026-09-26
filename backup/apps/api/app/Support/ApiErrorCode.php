<?php

declare(strict_types=1);

namespace App\Support;

enum ApiErrorCode: string
{
    case AuthenticationRequired = 'AUTHENTICATION_REQUIRED';
    case AuthorizationDenied = 'AUTHORIZATION_DENIED';
    case BadRequest = 'BAD_REQUEST';
    case Conflict = 'CONFLICT';
    case CsrfTokenMismatch = 'CSRF_TOKEN_MISMATCH';
    case InternalError = 'INTERNAL_ERROR';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case RateLimited = 'RATE_LIMITED';
    case ReauthenticationRequired = 'REAUTHENTICATION_REQUIRED';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case ValidationFailed = 'VALIDATION_FAILED';
}
