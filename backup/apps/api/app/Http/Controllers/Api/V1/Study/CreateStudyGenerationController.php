<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Study;

use App\Domains\Study\Actions\RequestStudyGenerationAction;
use App\Http\Requests\Api\V1\Study\CreateStudyGenerationRequest;
use App\Http\Resources\StudyArtifactResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateStudyGenerationController
{
    public function __invoke(
        CreateStudyGenerationRequest $request,
        string $item,
        RequestStudyGenerationAction $action,
    ): JsonResponse {
        $artifact = $action->execute($request->authenticatedUser(), $item, $request->kind());

        return ApiResponse::success(StudyArtifactResource::make($artifact)->resolve($request), status: 202);
    }
}
