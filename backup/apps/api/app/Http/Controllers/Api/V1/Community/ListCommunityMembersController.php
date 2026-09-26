<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListCommunityMembers;
use App\Http\Requests\Api\V1\Community\ListCommunityMembersRequest;
use App\Http\Resources\CommunityMemberResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCommunityMembersController
{
    public function __invoke(ListCommunityMembersRequest $request, string $community, ListCommunityMembers $members): JsonResponse
    {
        $result = $members->execute($request->authenticatedUser(), $community, $request->perPage());

        return ApiResponse::collection(
            CommunityMemberResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
