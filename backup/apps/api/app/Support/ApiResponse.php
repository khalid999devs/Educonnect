<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        $requestId = RequestId::getOrCreate(request());
        $payload = [
            'data' => $data,
            'meta' => [
                ...$meta,
                'request_id' => $requestId,
            ],
        ];

        $response = response()->json($payload, $status);
        RequestId::attach($response, $requestId);

        return $response;
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @param  CursorPaginator<int, mixed>  $paginator
     * @param  array<string, mixed>  $meta
     */
    public static function collection(
        array $data,
        CursorPaginator $paginator,
        array $meta = [],
    ): JsonResponse {
        $requestId = RequestId::getOrCreate(request());
        $payload = [
            'data' => $data,
            'meta' => [
                ...$meta,
                'pagination' => [
                    'next_cursor' => $paginator->nextCursor()?->encode(),
                    'previous_cursor' => $paginator->previousCursor()?->encode(),
                    'per_page' => $paginator->perPage(),
                ],
                'request_id' => $requestId,
            ],
            'links' => [
                'next' => $paginator->nextPageUrl(),
                'previous' => $paginator->previousPageUrl(),
            ],
        ];

        $response = response()->json($payload);
        RequestId::attach($response, $requestId);

        return $response;
    }

    public static function noContent(): Response
    {
        $requestId = RequestId::getOrCreate(request());
        $response = response()->noContent();
        RequestId::attach($response, $requestId);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, string|array<int, string>>  $headers
     */
    public static function error(
        ApiErrorCode $code,
        string $message,
        int $status,
        array $details = [],
        array $headers = [],
    ): JsonResponse {
        $requestId = RequestId::getOrCreate(request());
        $error = [
            'code' => $code->value,
            'message' => $message,
        ];

        if ($details !== []) {
            $error['details'] = $details;
        }

        $error['request_id'] = $requestId;

        $response = response()->json(['error' => $error], $status, $headers);
        RequestId::attach($response, $requestId);

        return $response;
    }
}
