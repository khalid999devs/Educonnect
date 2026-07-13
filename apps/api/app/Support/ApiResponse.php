<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

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
