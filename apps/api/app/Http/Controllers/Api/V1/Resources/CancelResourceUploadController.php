<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\CancelFileUploadAction;
use App\Http\Requests\Api\V1\Resources\VersionedResourceMutationRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CancelResourceUploadController
{
    public function __invoke(
        VersionedResourceMutationRequest $request,
        string $resource,
        CancelFileUploadAction $cancel,
    ): JsonResponse {
        $cancelled = $cancel->execute(
            $request->authenticatedUser(),
            $resource,
            $request->expectedVersion(),
        );

        return ApiResponse::success(
            ResourceResource::make($cancelled)->resolve($request),
            status: 202,
        );
    }
}
