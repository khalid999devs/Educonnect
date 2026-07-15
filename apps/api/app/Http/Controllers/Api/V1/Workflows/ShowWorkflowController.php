<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Domains\Guidance\Queries\FindPublishedWorkflow;
use App\Http\Requests\Api\V1\Guidance\EmptyGuidanceRequest;
use App\Http\Resources\WorkflowRecipeResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowWorkflowController
{
    public function __invoke(
        EmptyGuidanceRequest $request,
        string $workflow,
        FindPublishedWorkflow $workflows,
    ): JsonResponse {
        $publishedWorkflow = $workflows->execute($request->authenticatedUser(), $workflow);

        return ApiResponse::success(WorkflowRecipeResource::make($publishedWorkflow)->resolve($request));
    }
}
