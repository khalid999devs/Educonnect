<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\CreateTaskAction;
use App\Http\Requests\Api\V1\Planner\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateTaskController
{
    public function __invoke(StoreTaskRequest $request, CreateTaskAction $create): JsonResponse
    {
        $task = $create->execute($request->authenticatedUser(), $request->taskData());

        return ApiResponse::success(TaskResource::make($task)->resolve($request), status: 201);
    }
}
