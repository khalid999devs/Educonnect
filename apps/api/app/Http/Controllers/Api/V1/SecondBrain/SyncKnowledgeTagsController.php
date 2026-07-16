<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\SyncKnowledgeItemTagsAction;
use App\Http\Requests\Api\V1\SecondBrain\SyncKnowledgeTagsRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SyncKnowledgeTagsController
{
    public function __invoke(SyncKnowledgeTagsRequest $request, string $item, SyncKnowledgeItemTagsAction $sync): JsonResponse
    {
        $record = $sync->execute($request->authenticatedUser(), $item, $request->tagNames());

        return ApiResponse::success(KnowledgeItemResource::make($record)->resolve($request));
    }
}
