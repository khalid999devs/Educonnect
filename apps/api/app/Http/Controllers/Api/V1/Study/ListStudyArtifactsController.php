<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Study;

use App\Domains\Study\Queries\ListOwnedStudyArtifacts;
use App\Http\Requests\Api\V1\Study\ListStudyArtifactsRequest;
use App\Http\Resources\StudyArtifactResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListStudyArtifactsController
{
    public function __invoke(ListStudyArtifactsRequest $request, ListOwnedStudyArtifacts $artifacts): JsonResponse
    {
        $result = $artifacts->execute(
            $request->authenticatedUser(),
            $request->itemId(),
            $request->kind(),
            $request->status(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            StudyArtifactResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
