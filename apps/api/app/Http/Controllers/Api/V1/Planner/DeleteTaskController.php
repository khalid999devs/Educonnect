<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\DeleteTaskAction;
use App\Http\Requests\Api\V1\Planner\VersionedPlannerMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteTaskController
{
    public function __invoke(
        VersionedPlannerMutationRequest $request,
        string $task,
        DeleteTaskAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $task, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
