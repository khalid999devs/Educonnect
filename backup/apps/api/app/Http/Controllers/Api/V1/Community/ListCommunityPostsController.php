<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListCommunityPosts;
use App\Http\Requests\Api\V1\Community\ListCommunityPostsRequest;
use App\Http\Resources\CommunityPostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCommunityPostsController
{
    public function __invoke(ListCommunityPostsRequest $request, string $community, ListCommunityPosts $posts): JsonResponse
    {
        $result = $posts->execute($request->authenticatedUser(), $community, $request->perPage());

        return ApiResponse::collection(
            CommunityPostResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
