<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\ResolveReportAction;
use App\Http\Requests\Api\V1\Community\ResolveReportRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Http\JsonResponse;

final class ResolveReportController
{
    public function __invoke(ResolveReportRequest $request, string $report, ResolveReportAction $action): JsonResponse
    {
        $note = $request->note();

        $model = $action->execute(
            $request->authenticatedUser(),
            $report,
            $request->resolution(),
            $request->hideContent(),
            $note,
            $request->expectedVersion(),
            // The optional moderator note doubles as the audit reason; absent that,
            // the resolution itself is the immutable record of what was decided.
            $note ?? 'Report resolved as '.$request->resolution().'.',
            RequestId::getOrCreate($request),
        );

        return ApiResponse::success(ContentReportResource::make($model)->resolve($request));
    }
}
