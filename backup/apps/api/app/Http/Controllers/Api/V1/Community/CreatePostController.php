<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\CreatePostAction;
use App\Http\Requests\Api\V1\Community\StorePostRequest;
use App\Http\Resources\CommunityPostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreatePostController
{
    public function __invoke(StorePostRequest $request, string $community, CreatePostAction $action): JsonResponse
    {
        $post = $action->execute(
            $request->authenticatedUser(),
            $community,
            $request->title(),
            $request->body(),
            $request->sharedResourceId(),
        );

        return ApiResponse::success(CommunityPostResource::make($post)->resolve($request), status: 201);
    }
}
