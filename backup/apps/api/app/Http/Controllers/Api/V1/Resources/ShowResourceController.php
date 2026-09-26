<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Queries\FindOwnedResource;
use App\Http\Requests\Api\V1\Resources\EmptyResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowResourceController
{
    public function __invoke(
        EmptyResourceRequest $request,
        string $resource,
        FindOwnedResource $resources,
    ): JsonResponse {
        $ownedResource = $resources->execute($request->authenticatedUser(), $resource);

        return ApiResponse::success(ResourceResource::make($ownedResource)->resolve($request));
    }
}
