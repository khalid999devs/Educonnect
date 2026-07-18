<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListFeedPosts;
use App\Http\Requests\Api\V1\Community\ListFeedRequest;
use App\Http\Resources\CommunityPostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListFeedController
{
    public function __invoke(ListFeedRequest $request, ListFeedPosts $feed): JsonResponse
    {
        $result = $feed->execute($request->authenticatedUser(), $request->perPage());

        return ApiResponse::collection(
            CommunityPostResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
