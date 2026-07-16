<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\CreateResearchTopicAction;
use App\Http\Requests\Api\V1\SecondBrain\StoreResearchTopicRequest;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateResearchTopicController
{
    public function __invoke(StoreResearchTopicRequest $request, CreateResearchTopicAction $create): JsonResponse
    {
        $topic = $create->execute($request->authenticatedUser(), $request->topicData());

        return ApiResponse::success(ResearchTopicResource::make($topic)->resolve($request), status: 201);
    }
}
