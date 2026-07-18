<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Reports;

use App\Domains\Community\Queries\ListModerationReports;
use App\Http\Requests\Api\V1\Admin\ListAdminReportsRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminReportsController
{
    public function __invoke(ListAdminReportsRequest $request, ListModerationReports $reports): JsonResponse
    {
        // Reuses the moderation query: results are already scoped to the caller's
        // moderation remit (assigned communities, or all when globally scoped).
        $result = $reports->execute($request->adminUser(), $request->status(), $request->perPage());

        return ApiResponse::collection(
            ContentReportResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
        );
    }
}
