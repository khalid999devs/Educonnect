<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Queries\ListPostComments;
use App\Http\Requests\Api\V1\Community\ListCommentsRequest;
use App\Http\Resources\CommunityCommentResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCommentsController
{
    public function __invoke(ListCommentsRequest $request, string $post, ListPostComments $comments): JsonResponse
    {
        $result = $comments->execute($request->authenticatedUser(), $post, $request->perPage());

        return ApiResponse::collection(
            CommunityCommentResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
