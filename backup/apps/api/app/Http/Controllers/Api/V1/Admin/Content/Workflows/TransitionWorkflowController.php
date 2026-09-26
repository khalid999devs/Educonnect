<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Workflows;

use App\Domains\Content\Actions\TransitionContentAction;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Http\Requests\Api\V1\Admin\Content\TransitionContentRequest;
use App\Http\Resources\AdminWorkflowResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TransitionWorkflowController
{
    public function __invoke(TransitionContentRequest $request, WorkflowRecipe $workflow, TransitionContentAction $action): JsonResponse
    {
        $result = $action->execute(
            $request->adminUser(),
            $workflow,
            'workflow_recipe',
            $request->transition(),
            $request->expectedVersion(),
            $request->reason(),
            $request->requestId(),
        );

        return ApiResponse::success(AdminWorkflowResource::make($result->load(['category', 'steps']))->resolve($request));
    }
}
