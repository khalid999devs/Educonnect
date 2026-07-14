<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Queries\FindOwnedTask;
use App\Domains\Users\Models\User;
use App\Http\Resources\TaskResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowTaskController
{
    public function __invoke(Request $request, string $task, FindOwnedTask $tasks): JsonResponse
    {
        $resource = $tasks->execute($this->user($request), $task);

        return ApiResponse::success(TaskResource::make($resource)->resolve($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
