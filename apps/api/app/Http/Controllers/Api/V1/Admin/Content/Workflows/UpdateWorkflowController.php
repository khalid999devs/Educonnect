<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Workflows;

use App\Domains\Guidance\Actions\UpdateWorkflowAction;
use App\Domains\Guidance\Models\WorkflowRecipe;
use App\Http\Requests\Api\V1\Admin\Content\UpdateWorkflowRequest;
use App\Http\Resources\AdminWorkflowResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateWorkflowController
{
    public function __invoke(UpdateWorkflowRequest $request, WorkflowRecipe $workflow, UpdateWorkflowAction $action): JsonResponse
    {
        $updated = $action->execute($request->adminUser(), $workflow, $request->contentData(), $request->expectedVersion());

        return ApiResponse::success(AdminWorkflowResource::make($updated)->resolve($request));
    }
}
