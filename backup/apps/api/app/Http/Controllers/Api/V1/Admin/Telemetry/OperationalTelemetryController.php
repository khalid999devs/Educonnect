<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Telemetry;

use App\Domains\Telemetry\Queries\BuildTelemetryOverview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OperationalTelemetryController
{
    public function __invoke(BuildTelemetryOverview $overview): JsonResponse
    {
        return ApiResponse::success($overview->execute());
    }
}
