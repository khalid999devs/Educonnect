<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Queries\ListOwnedTasks;
use App\Http\Requests\Api\V1\Planner\ListTasksRequest;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListTasksController
{
    public function __invoke(ListTasksRequest $request, ListOwnedTasks $tasks): JsonResponse
    {
        $paginator = $tasks->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->status(),
            $request->archiveStatus(),
            $request->courseId(),
            $request->dueFrom(),
            $request->dueBefore(),
            $request->hasDue(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            TaskResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
