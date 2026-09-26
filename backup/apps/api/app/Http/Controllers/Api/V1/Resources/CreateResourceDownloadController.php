<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Resources;

use App\Domains\Resources\Actions\CreateFileDownloadAction;
use App\Http\Requests\Api\V1\Resources\EmptyResourceRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateResourceDownloadController
{
    public function __invoke(
        EmptyResourceRequest $request,
        string $resource,
        CreateFileDownloadAction $download,
    ): JsonResponse {
        $result = $download->execute($request->authenticatedUser(), $resource);

        return ApiResponse::success([
            'url' => $result->url,
            'expires_at' => $result->expiresAt->toISOString(),
        ]);
    }
}
