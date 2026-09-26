<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\CreateKnowledgeNoteAction;
use App\Http\Requests\Api\V1\SecondBrain\StoreKnowledgeNoteRequest;
use App\Http\Resources\KnowledgeNoteResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateKnowledgeNoteController
{
    public function __invoke(StoreKnowledgeNoteRequest $request, string $item, CreateKnowledgeNoteAction $create): JsonResponse
    {
        $note = $create->execute($request->authenticatedUser(), $item, $request->body());

        return ApiResponse::success(KnowledgeNoteResource::make($note)->resolve($request), status: 201);
    }
}
