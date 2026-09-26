<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListPublishedCommunities;
use App\Http\Requests\Api\V1\Community\ListCommunitiesRequest;
use App\Http\Resources\CommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCommunitiesController
{
    public function __invoke(ListCommunitiesRequest $request, ListPublishedCommunities $communities): JsonResponse
    {
        $result = $communities->execute($request->authenticatedUser(), $request->search(), $request->perPage());

        return ApiResponse::collection(
            CommunityResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
