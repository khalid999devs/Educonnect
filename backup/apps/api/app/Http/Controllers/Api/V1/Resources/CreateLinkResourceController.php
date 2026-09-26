<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\CreateLinkAction;
use App\Http\Requests\Api\V1\Resources\StoreLinkResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateLinkResourceController
{
    public function __invoke(StoreLinkResourceRequest $request, CreateLinkAction $create): JsonResponse
    {
        $resource = $create->execute($request->authenticatedUser(), $request->resourceData());

        return ApiResponse::success(ResourceResource::make($resource)->resolve($request), status: 201);
    }
}
