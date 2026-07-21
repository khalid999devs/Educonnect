<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\ListOwnedKnowledgeItems;
use App\Http\Requests\Api\V1\SecondBrain\ListKnowledgeItemsRequest;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListKnowledgeItemsController
{
    public function __invoke(ListKnowledgeItemsRequest $request, ListOwnedKnowledgeItems $items): JsonResponse
    {
        $result = $items->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->collectionId(),
            $request->tag(),
            $request->sourceType(),
            $request->purpose(),
            $request->saved(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            KnowledgeItemResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
