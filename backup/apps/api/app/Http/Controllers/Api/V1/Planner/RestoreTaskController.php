<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\SetTaskArchiveStateAction;
use App\Http\Requests\Api\V1\Planner\VersionedPlannerMutationRequest;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RestoreTaskController
{
    public function __invoke(
        VersionedPlannerMutationRequest $request,
        string $task,
        SetTaskArchiveStateAction $restore,
    ): JsonResponse {
        $resource = $restore->execute(
            $request->authenticatedUser(),
            $task,
            archived: false,
            expectedVersion: $request->expectedVersion(),
        );

        return ApiResponse::success(TaskResource::make($resource)->resolve($request));
    }
}
