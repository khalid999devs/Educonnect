<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\CreateCommentAction;
use App\Http\Requests\Api\V1\Community\StoreCommentRequest;
use App\Http\Resources\CommunityCommentResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCommentController
{
    public function __invoke(StoreCommentRequest $request, string $post, CreateCommentAction $action): JsonResponse
    {
        $comment = $action->execute($request->authenticatedUser(), $post, $request->body());

        return ApiResponse::success(CommunityCommentResource::make($comment)->resolve($request), status: 201);
    }
}
