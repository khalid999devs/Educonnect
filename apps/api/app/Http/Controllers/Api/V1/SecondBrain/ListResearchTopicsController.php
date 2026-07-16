<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\ListOwnedResearchTopics;
use App\Http\Requests\Api\V1\SecondBrain\ListResearchTopicsRequest;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListResearchTopicsController
{
    public function __invoke(ListResearchTopicsRequest $request, ListOwnedResearchTopics $topics): JsonResponse
    {
        $result = $topics->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            ResearchTopicResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
