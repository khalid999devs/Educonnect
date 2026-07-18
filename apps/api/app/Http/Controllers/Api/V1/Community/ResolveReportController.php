<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\ResolveReportAction;
use App\Http\Requests\Api\V1\Community\ResolveReportRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ResolveReportController
{
    public function __invoke(ResolveReportRequest $request, string $report, ResolveReportAction $action): JsonResponse
    {
        $model = $action->execute(
            $request->authenticatedUser(),
            $report,
            $request->resolution(),
            $request->hideContent(),
            $request->note(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(ContentReportResource::make($model)->resolve($request));
    }
}
