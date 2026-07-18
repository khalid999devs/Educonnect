<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Reports;

use App\Domains\Community\Actions\ResolveReportAction;
use App\Http\Requests\Api\V1\Admin\ResolveAdminReportRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ResolveAdminReportController
{
    public function __invoke(
        ResolveAdminReportRequest $request,
        string $report,
        ResolveReportAction $action,
    ): JsonResponse {
        $model = $action->execute(
            $request->adminUser(),
            $report,
            $request->resolution(),
            $request->hideContent(),
            $request->note(),
            $request->expectedVersion(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(ContentReportResource::make($model)->resolve($request));
    }
}
