<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListModerationReports;
use App\Http\Requests\Api\V1\Community\ListModerationReportsRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListModerationReportsController
{
    public function __invoke(ListModerationReportsRequest $request, ListModerationReports $reports): JsonResponse
    {
        $result = $reports->execute($request->authenticatedUser(), $request->status(), $request->perPage());

        return ApiResponse::collection(
            ContentReportResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
