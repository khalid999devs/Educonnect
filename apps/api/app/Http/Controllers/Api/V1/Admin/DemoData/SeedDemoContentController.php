<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\DemoData;

use App\Domains\Admin\Actions\SeedDemoContentAction;
use App\Http\Requests\Api\V1\Admin\SeedDemoContentRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SeedDemoContentController
{
    public function __invoke(SeedDemoContentRequest $request, SeedDemoContentAction $action): JsonResponse
    {
        $summary = $action->execute(
            $request->adminUser(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(['catalog' => $summary]);
    }
}
