<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Community;

use App\Domains\Community\Queries\ListCommunitiesForAdmin;
use App\Http\Requests\Api\V1\Admin\Community\ListAdminCommunitiesRequest;
use App\Http\Resources\AdminCommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCommunitiesController
{
    public function __invoke(ListAdminCommunitiesRequest $request, ListCommunitiesForAdmin $communities): JsonResponse
    {
        $paginator = $communities->execute($request->visibility(), $request->perPage());

        return ApiResponse::collection(
            AdminCommunityResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
