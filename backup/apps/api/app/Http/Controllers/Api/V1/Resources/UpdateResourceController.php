<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\UpdateResourceAction;
use App\Http\Requests\Api\V1\Resources\UpdateResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateResourceController
{
    public function __invoke(
        UpdateResourceRequest $request,
        string $resource,
        UpdateResourceAction $update,
    ): JsonResponse {
        $updated = $update->execute(
            $request->authenticatedUser(),
            $resource,
            $request->resourceData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(ResourceResource::make($updated)->resolve($request));
    }
}
