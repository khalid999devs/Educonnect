<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\RetryFileUploadAction;
use App\Domains\Resources\Data\UploadGrantResult;
use App\Http\Requests\Api\V1\Resources\VersionedResourceMutationRequest;
use App\Http\Resources\ResourceResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RetryResourceUploadController
{
    public function __invoke(
        VersionedResourceMutationRequest $request,
        string $resource,
        RetryFileUploadAction $retry,
    ): JsonResponse {
        $result = $retry->execute(
            $request->authenticatedUser(),
            $resource,
            $request->expectedVersion(),
        );

        return ApiResponse::success($this->payload($request, $result));
    }

    /** @return array{resource: array<string, mixed>, upload: array{method: string, url: string, headers: array<string, string>, expires_at: string}} */
    private function payload(VersionedResourceMutationRequest $request, UploadGrantResult $result): array
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
