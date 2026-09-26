<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\SaveKnowledgeItemAction;
use App\Http\Requests\Api\V1\SecondBrain\EmptyBrainRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SaveKnowledgeItemController
{
    public function __invoke(
        EmptyBrainRequest $request,
        string $item,
        SaveKnowledgeItemAction $save,
    ): JsonResponse {
        $record = $save->execute($request->authenticatedUser(), $item);

        return ApiResponse::success(KnowledgeItemResource::make($record)->resolve($request));
    }
}
