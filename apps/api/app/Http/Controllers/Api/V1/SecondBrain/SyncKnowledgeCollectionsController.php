<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\SyncKnowledgeItemCollectionsAction;
use App\Http\Requests\Api\V1\SecondBrain\SyncKnowledgeCollectionsRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SyncKnowledgeCollectionsController
{
    public function __invoke(
        SyncKnowledgeCollectionsRequest $request,
        string $item,
        SyncKnowledgeItemCollectionsAction $sync,
    ): JsonResponse {
        $record = $sync->execute($request->authenticatedUser(), $item, $request->collectionIds());

        return ApiResponse::success(KnowledgeItemResource::make($record)->resolve($request));
    }
}
