<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\FindPost;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiUser;
use App\Http\Resources\CommunityPostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowPostController
{
    use InteractsWithApiUser;

    public function __invoke(Request $request, string $post, FindPost $posts): JsonResponse
    {
        $model = $posts->execute($this->apiUser($request), $post);

        return ApiResponse::success(CommunityPostResource::make($model)->resolve($request));
    }
}
