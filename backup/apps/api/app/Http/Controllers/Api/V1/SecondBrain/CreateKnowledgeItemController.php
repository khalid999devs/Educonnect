<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\CreateKnowledgeItemAction;
use App\Http\Requests\Api\V1\SecondBrain\StoreKnowledgeItemRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateKnowledgeItemController
{
    public function __invoke(StoreKnowledgeItemRequest $request, CreateKnowledgeItemAction $create): JsonResponse
    {
        $item = $create->execute($request->authenticatedUser(), $request->knowledgeItemData());

        return ApiResponse::success(KnowledgeItemResource::make($item)->resolve($request), status: 201);
    }
}
