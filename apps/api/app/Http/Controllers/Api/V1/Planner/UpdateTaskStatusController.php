<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\SetTaskStatusAction;
use App\Http\Requests\Api\V1\Planner\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateTaskStatusController
{
    public function __invoke(
        UpdateTaskStatusRequest $request,
        string $task,
        SetTaskStatusAction $update,
    ): JsonResponse {
        $resource = $update->execute(
            $request->authenticatedUser(),
            $task,
            $request->status(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(TaskResource::make($resource)->resolve($request));
    }
}
