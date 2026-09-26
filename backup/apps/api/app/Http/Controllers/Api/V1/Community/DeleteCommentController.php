<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\DeleteCommentAction;
use App\Http\Requests\Api\V1\Community\CommunityVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteCommentController
{
    public function __invoke(CommunityVersionedMutationRequest $request, string $comment, DeleteCommentAction $action): Response
    {
        $action->execute($request->authenticatedUser(), $comment, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
