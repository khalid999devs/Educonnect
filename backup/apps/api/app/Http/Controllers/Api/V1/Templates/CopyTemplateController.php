<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Templates;

use App\Domains\Templates\Actions\CopyTemplateAction;
use App\Http\Requests\Api\V1\Templates\CopyTemplateRequest;
use App\Http\Resources\TemplateCopyResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CopyTemplateController
{
    public function __invoke(
        CopyTemplateRequest $request,
        string $template,
        CopyTemplateAction $copies,
    ): JsonResponse {
        $copy = $copies->execute(
            $request->authenticatedUser(),
            $template,
            $request->destination(),
            $request->coursePublicId(),
        );

        return ApiResponse::success(
            TemplateCopyResource::make($copy)->resolve($request),
            status: $copy->wasRecentlyCreated ? 201 : 200,
        );
    }
}
