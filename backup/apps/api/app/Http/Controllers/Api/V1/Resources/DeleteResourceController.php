<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\DeleteResourceAction;
use App\Http\Requests\Api\V1\Resources\VersionedResourceMutationRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class DeleteResourceController
{
    public function __invoke(
        VersionedResourceMutationRequest $request,
        string $resource,
        DeleteResourceAction $delete,
    ): JsonResponse|Response {
        $result = $delete->execute(
            $request->authenticatedUser(),
            $resource,
            $request->expectedVersion(),
        );

        if (! $result->pending || $result->resource === null) {
            return ApiResponse::noContent();
        }

        return ApiResponse::success(
            ResourceResource::make($result->resource)->resolve($request),
            status: 202,
        );
    }
}
