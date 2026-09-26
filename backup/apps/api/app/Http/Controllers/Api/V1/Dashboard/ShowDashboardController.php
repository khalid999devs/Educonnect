<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Domains\Dashboard\Queries\BuildDashboard;
use App\Http\Requests\Api\V1\Dashboard\ShowDashboardRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowDashboardController
{
    public function __invoke(ShowDashboardRequest $request, BuildDashboard $dashboard): JsonResponse
    {
        $payload = $dashboard->execute($request->authenticatedUser(), $request->timezone());

        return ApiResponse::success($payload);
    }
}
