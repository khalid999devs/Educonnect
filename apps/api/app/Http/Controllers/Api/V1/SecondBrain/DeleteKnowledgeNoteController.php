<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\DeleteKnowledgeNoteAction;
use App\Http\Requests\Api\V1\SecondBrain\BrainVersionedMutationRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class DeleteKnowledgeNoteController
{
    public function __invoke(
        BrainVersionedMutationRequest $request,
        string $item,
        string $note,
        DeleteKnowledgeNoteAction $delete,
    ): Response {
        $delete->execute($request->authenticatedUser(), $item, $note, $request->expectedVersion());

        return ApiResponse::noContent();
    }
}
