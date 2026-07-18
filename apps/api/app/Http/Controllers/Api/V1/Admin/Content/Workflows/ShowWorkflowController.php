<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Workflows;

use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Http\Resources\AdminWorkflowResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowWorkflowController
{
    public function __invoke(Request $request, WorkflowRecipe $workflow): JsonResponse
    {
        return ApiResponse::success(
            AdminWorkflowResource::make($workflow->load(['category', 'steps']))->resolve($request),
        );
    }
}
