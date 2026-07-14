<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\UpdateTaskAction;
use App\Http\Requests\Api\V1\Planner\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateTaskController
{
    public function __invoke(UpdateTaskRequest $request, string $task, UpdateTaskAction $update): JsonResponse
    {
        $resource = $update->execute(
            $request->authenticatedUser(),
            $task,
            $request->taskData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(TaskResource::make($resource)->resolve($request));
    }
}
