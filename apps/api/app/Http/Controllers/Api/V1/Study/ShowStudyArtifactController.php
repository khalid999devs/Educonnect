<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Study;

use App\Domains\Study\Queries\FindOwnedStudyArtifact;
use App\Http\Requests\Api\V1\Study\ShowStudyArtifactRequest;
use App\Http\Resources\StudyArtifactResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowStudyArtifactController
{
    public function __invoke(
        ShowStudyArtifactRequest $request,
        string $artifact,
        FindOwnedStudyArtifact $artifacts,
    ): JsonResponse {
        $found = $artifacts->execute($request->authenticatedUser(), $artifact);

        return ApiResponse::success(StudyArtifactResource::make($found)->resolve($request));
    }
}
