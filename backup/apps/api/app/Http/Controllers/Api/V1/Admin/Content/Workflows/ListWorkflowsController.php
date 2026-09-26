<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content\Workflows;

use App\Domains\Guidance\Queries\ListWorkflowsForAdmin;
use App\Http\Requests\Api\V1\Admin\Content\ListAdminContentRequest;
use App\Http\Resources\AdminWorkflowResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListWorkflowsController
{
    public function __invoke(ListAdminContentRequest $request, ListWorkflowsForAdmin $workflows): JsonResponse
    {
        $paginator = $workflows->execute($request->state(), $request->perPage());

        return ApiResponse::collection(
            AdminWorkflowResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
