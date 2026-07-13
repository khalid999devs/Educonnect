<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Health;

use App\Http\Controllers\Controller;
use App\Support\ApiErrorCode;
use App\Support\ApiResponse;
use App\Support\Health\ReadinessCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReadinessController extends Controller
{
    public function __invoke(ReadinessCheck $readiness): JsonResponse
    {
        try {
            if ($readiness->passes()) {
                return ApiResponse::success([
                    'status' => 'ready',
                    'checks' => [
                        'database' => 'up',
                    ],
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Readiness check failed.', [
                'check' => 'database',
                'outcome' => 'down',
                'exception_type' => $exception::class,
                'exception_code' => $exception->getCode(),
            ]);
        }

        return ApiResponse::error(
            code: ApiErrorCode::ServiceUnavailable,
            message: 'The service is not ready to receive traffic.',
            status: 503,
        );
    }
}
