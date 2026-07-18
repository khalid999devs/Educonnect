<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\FindCommunity;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiUser;
use App\Http\Resources\CommunityResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowCommunityController
{
    use InteractsWithApiUser;

    public function __invoke(Request $request, string $community, FindCommunity $communities): JsonResponse
    {
        $model = $communities->execute($this->apiUser($request), $community);

        return ApiResponse::success(CommunityResource::make($model)->resolve($request));
    }
}
