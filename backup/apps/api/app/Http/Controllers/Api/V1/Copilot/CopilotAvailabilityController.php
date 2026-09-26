<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Copilot;

use App\Support\Ai\OpenAiClient;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CopilotAvailabilityController
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'copilot' => [
                'enabled' => OpenAiClient::configured(),
                'model' => (string) config('ai.models.copilot'),
            ],
        ]);
    }
}
