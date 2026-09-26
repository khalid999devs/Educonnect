<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Workflows;

use App\Domains\Guidance\Actions\CreateWorkflowAction;
use App\Http\Requests\Api\V1\Admin\Content\CreateWorkflowRequest;
use App\Http\Resources\AdminWorkflowResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateWorkflowController
{
    public function __invoke(CreateWorkflowRequest $request, CreateWorkflowAction $action): JsonResponse
    {
        $workflow = $action->execute($request->adminUser(), $request->contentData());

        return ApiResponse::success(AdminWorkflowResource::make($workflow)->resolve($request), [], 201);
    }
}
