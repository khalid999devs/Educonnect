<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DeleteKnowledgeItemAction;
use App\Http\Requests\Api\V1\SecondBrain\BrainVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteKnowledgeItemController
{
    public function __invoke(
        BrainVersionedMutationRequest $request,
        string $item,
        DeleteKnowledgeItemAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $item, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
