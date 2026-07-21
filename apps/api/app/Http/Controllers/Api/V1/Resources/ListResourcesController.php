<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Queries\ListOwnedResources;
use App\Http\Requests\Api\V1\Resources\ListResourcesRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListResourcesController
{
    public function __invoke(ListResourcesRequest $request, ListOwnedResources $resources): JsonResponse
    {
        $paginator = $resources->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->kind(),
            $request->courseId(),
            $request->unfiledOnly(),
            $request->topic(),
            $request->fileStatus(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            ResourceResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
