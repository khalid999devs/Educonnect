<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DeleteKnowledgeLinkAction;
use App\Http\Requests\Api\V1\SecondBrain\EmptyBrainRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteKnowledgeLinkController
{
    public function __invoke(
        EmptyBrainRequest $request,
        string $item,
        string $link,
        DeleteKnowledgeLinkAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $item, $link);

        return ApiResponse::noContent();
    }
}
