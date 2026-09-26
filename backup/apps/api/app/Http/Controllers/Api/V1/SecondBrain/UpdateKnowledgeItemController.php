<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UpdateKnowledgeItemAction;
use App\Http\Requests\Api\V1\SecondBrain\UpdateKnowledgeItemRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateKnowledgeItemController
{
    public function __invoke(UpdateKnowledgeItemRequest $request, string $item, UpdateKnowledgeItemAction $update): JsonResponse
    {
        $record = $update->execute(
            $request->authenticatedUser(),
            $item,
            $request->knowledgeItemData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(KnowledgeItemResource::make($record)->resolve($request));
    }
}
