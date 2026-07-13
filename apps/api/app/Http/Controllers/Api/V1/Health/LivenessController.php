<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Health;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return ApiResponse::success([
            'status' => 'up',
        ]);
    }
}
