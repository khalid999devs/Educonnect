<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UpdateKnowledgeNoteAction;
use App\Http\Requests\Api\V1\SecondBrain\UpdateKnowledgeNoteRequest;
use App\Http\Resources\KnowledgeNoteResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateKnowledgeNoteController
{
    public function __invoke(
        UpdateKnowledgeNoteRequest $request,
        string $item,
        string $note,
        UpdateKnowledgeNoteAction $update,
    ): JsonResponse {
        $record = $update->execute(
            $request->authenticatedUser(),
            $item,
            $note,
            $request->body(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(KnowledgeNoteResource::make($record)->resolve($request));
    }
}
