<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Queries\ListOwnedFocusSessions;
use App\Http\Requests\Api\V1\Planner\ListFocusSessionsRequest;
use App\Http\Resources\FocusSessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListFocusSessionsController
{
    public function __invoke(
        ListFocusSessionsRequest $request,
        ListOwnedFocusSessions $sessions,
    ): JsonResponse {
        $paginator = $sessions->execute(
            $request->authenticatedUser(),
            $request->taskId(),
            $request->courseId(),
            $request->overlapFrom(),
            $request->overlapBefore(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            FocusSessionResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
