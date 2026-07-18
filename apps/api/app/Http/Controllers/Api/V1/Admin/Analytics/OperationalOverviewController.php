<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Domains\Admin\Queries\BuildOperationalOverview;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class OperationalOverviewController
{
    public function __invoke(BuildOperationalOverview $overview): JsonResponse
    {
        return ApiResponse::success($overview->execute());
    }
}
