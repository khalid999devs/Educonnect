<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\CreateReportAction;
use App\Http\Requests\Api\V1\Community\StoreReportRequest;
use App\Http\Resources\ContentReportResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCommentReportController
{
    public function __invoke(StoreReportRequest $request, string $comment, CreateReportAction $action): JsonResponse
    {
        $report = $action->execute($request->authenticatedUser(), 'comment', $comment, $request->reason(), $request->detail());

        return ApiResponse::success(ContentReportResource::make($report)->resolve($request), status: 201);
    }
}
