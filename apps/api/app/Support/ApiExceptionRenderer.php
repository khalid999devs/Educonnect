<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ApiExceptionRenderer
{
    /**
     * Headers whose meaning survives replacing the original representation body.
     *
     * @var list<string>
     */
    private const PRESERVED_HEADERS = [
        'access-control-allow-credentials',
        'access-control-allow-headers',
        'access-control-allow-methods',
        'access-control-allow-origin',
        'access-control-expose-headers',
        'access-control-max-age',
        'allow',
        'retry-after',
        'vary',
        'www-authenticate',
    ];

    public function render(Response $response, Throwable $exception, Request $request): JsonResponse
    {
        $status = $response->getStatusCode() >= 400
            ? $response->getStatusCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;
        [$code, $message] = $this->errorForStatus($status);
        $details = $exception instanceof ValidationException
            ? ['fields' => $exception->errors()]
            : [];

        return ApiResponse::error(
            code: $exception instanceof ValidationException ? ApiErrorCode::ValidationFailed : $code,
            message: $exception instanceof ValidationException ? 'Some fields need attention.' : $message,
            status: $exception instanceof ValidationException ? 422 : $status,
            details: $details,
            headers: $this->preservedHeaders($response),
        );
    }

    /**
     * @return array{ApiErrorCode, string}
     */
    private function errorForStatus(int $status): array
    {
        return match ($status) {
            400 => [ApiErrorCode::BadRequest, 'The request could not be processed.'],
            401 => [ApiErrorCode::AuthenticationRequired, 'Authentication is required.'],
            403 => [ApiErrorCode::AuthorizationDenied, 'You are not allowed to perform this action.'],
            404 => [ApiErrorCode::ResourceNotFound, 'The requested resource was not found.'],
            405 => [ApiErrorCode::MethodNotAllowed, 'The request method is not allowed for this resource.'],
            409 => [ApiErrorCode::Conflict, 'The request conflicts with the current resource state.'],
            419 => [ApiErrorCode::CsrfTokenMismatch, 'The CSRF token is invalid or expired.'],
            422 => [ApiErrorCode::ValidationFailed, 'Some fields need attention.'],
            429 => [ApiErrorCode::RateLimited, 'Too many requests. Please try again later.'],
            502, 503 => [ApiErrorCode::ServiceUnavailable, 'The service is temporarily unavailable.'],
            default => $status >= 500
                ? [ApiErrorCode::InternalError, 'An unexpected error occurred.']
                : [ApiErrorCode::BadRequest, 'The request could not be processed.'],
        };
    }

    /**
     * @return array<string, string|array<int, string>>
     */
    private function preservedHeaders(Response $response): array
    {
        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            $normalizedName = strtolower($name);
            $isRateLimitHeader = str_starts_with($normalizedName, 'ratelimit-')
                || str_starts_with($normalizedName, 'x-ratelimit-');

            if (! in_array($normalizedName, self::PRESERVED_HEADERS, true) && ! $isRateLimitHeader) {
                continue;
            }

            $headers[$name] = array_values(array_filter($values, is_string(...)));
        }

        return $headers;
    }
}
