<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\InitiateFileUploadAction;
use App\Domains\Resources\Data\UploadGrantResult;
use App\Http\Requests\Api\V1\Resources\StoreFileResourceRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class InitiateFileResourceController
{
    public function __invoke(
        StoreFileResourceRequest $request,
        InitiateFileUploadAction $initiate,
    ): JsonResponse {
        $result = $initiate->execute($request->authenticatedUser(), $request->resourceData());

        return ApiResponse::success($this->payload($request, $result), status: 201);
    }

    /** @return array{resource: array<string, mixed>, upload: array{method: string, url: string, headers: array<string, string>, expires_at: string}} */
    private function payload(StoreFileResourceRequest $request, UploadGrantResult $result): array
    {
        return [
            'resource' => ResourceResource::make($result->resource)->resolve($request),
            'upload' => [
                'method' => 'PUT',
                'url' => $result->url,
                'headers' => $result->headers,
                'expires_at' => $result->expiresAt->toISOString(),
            ],
        ];
    }
}
