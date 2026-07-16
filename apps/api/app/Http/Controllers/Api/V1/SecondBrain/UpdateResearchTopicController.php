<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UpdateResearchTopicAction;
use App\Http\Requests\Api\V1\SecondBrain\UpdateResearchTopicRequest;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateResearchTopicController
{
    public function __invoke(UpdateResearchTopicRequest $request, string $topic, UpdateResearchTopicAction $update): JsonResponse
    {
        $record = $update->execute(
            $request->authenticatedUser(),
            $topic,
            $request->topicData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(ResearchTopicResource::make($record)->resolve($request));
    }
}
