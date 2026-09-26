<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\UpdatePostAction;
use App\Http\Requests\Api\V1\Community\UpdatePostRequest;
use App\Http\Resources\CommunityPostResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdatePostController
{
    public function __invoke(UpdatePostRequest $request, string $post, UpdatePostAction $action): JsonResponse
    {
        $model = $action->execute(
            $request->authenticatedUser(),
            $post,
            $request->title(),
            $request->body(),
            $request->sharedResourceId(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(CommunityPostResource::make($model)->resolve($request));
    }
}
