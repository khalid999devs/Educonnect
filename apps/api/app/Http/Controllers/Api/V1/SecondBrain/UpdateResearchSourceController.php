<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UpdateResearchSourceAction;
use App\Http\Requests\Api\V1\SecondBrain\UpdateResearchSourceRequest;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateResearchSourceController
{
    public function __invoke(
        UpdateResearchSourceRequest $request,
        string $topic,
        string $item,
        UpdateResearchSourceAction $update,
    ): JsonResponse {
        $record = $update->execute(
            $request->authenticatedUser(),
            $topic,
            $item,
            $request->readingStatus(),
        );

        return ApiResponse::success(ResearchTopicResource::make($record)->resolve($request));
    }
}
