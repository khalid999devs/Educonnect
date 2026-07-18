<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Community;

use App\Domains\Community\Actions\DeletePostAction;
use App\Http\Requests\Api\V1\Community\CommunityVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeletePostController
{
    public function __invoke(CommunityVersionedMutationRequest $request, string $post, DeletePostAction $action): Response
    {
        $action->execute($request->authenticatedUser(), $post, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
