<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TemplateCopies;

use App\Domains\Templates\Actions\UpdateTemplateCopyAction;
use App\Http\Requests\Api\V1\Templates\UpdateTemplateCopyRequest;
use App\Http\Resources\TemplateCopyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateTemplateCopyController
{
    public function __invoke(
        UpdateTemplateCopyRequest $request,
        string $copy,
        UpdateTemplateCopyAction $updates,
    ): JsonResponse {
        $updated = $updates->execute(
            $request->authenticatedUser(),
            $copy,
            $request->copyData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(TemplateCopyResource::make($updated)->resolve($request));
    }
}
