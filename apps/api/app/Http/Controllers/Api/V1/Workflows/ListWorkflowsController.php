<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Workflows;

use App\Domains\Guidance\Queries\ListPublishedWorkflows;
use App\Http\Requests\Api\V1\Guidance\ListWorkflowsRequest;
use App\Http\Resources\WorkflowRecipeResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListWorkflowsController
{
    public function __invoke(ListWorkflowsRequest $request, ListPublishedWorkflows $workflows): JsonResponse
    {
        $paginator = $workflows->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->category(),
            $request->preference(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            WorkflowRecipeResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
