<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\TemplateCopies;

use App\Domains\Templates\Actions\SetTemplateCopyArchiveStateAction;
use App\Http\Requests\Api\V1\Templates\ArchiveTemplateCopyRequest;
use App\Http\Resources\TemplateCopyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class RestoreTemplateCopyController
{
    public function __invoke(
        ArchiveTemplateCopyRequest $request,
        string $copy,
        SetTemplateCopyArchiveStateAction $archives,
    ): JsonResponse {
        $restored = $archives->execute(
            $request->authenticatedUser(),
            $copy,
            archived: false,
            expectedVersion: $request->expectedVersion(),
        );

        return ApiResponse::success(TemplateCopyResource::make($restored)->resolve($request));
    }
}
