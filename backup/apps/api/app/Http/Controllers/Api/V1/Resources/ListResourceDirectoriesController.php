<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Queries\ListResourceDirectories;
use App\Http\Requests\Api\V1\Resources\ListResourceDirectoriesRequest;
use App\Http\Resources\ResourceDirectoryResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListResourceDirectoriesController
{
    public function __invoke(
        ListResourceDirectoriesRequest $request,
        ListResourceDirectories $directories,
    ): JsonResponse {
        $listing = $directories->execute($request->authenticatedUser());

        return ApiResponse::success(
            ResourceDirectoryResource::collection($listing)->resolve($request),
        );
    }
}
