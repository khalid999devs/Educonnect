<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\CreateKnowledgeLinkAction;
use App\Domains\SecondBrain\Models\KnowledgeItem;
use App\Http\Requests\Api\V1\SecondBrain\StoreKnowledgeLinkRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateKnowledgeLinkController
{
    public function __invoke(StoreKnowledgeLinkRequest $request, string $item, CreateKnowledgeLinkAction $create): JsonResponse
    {
        $link = $create->execute(
            $request->authenticatedUser(),
            $item,
            $request->targetId(),
            $request->relationType(),
        );
        $target = $link->toItem;

        return ApiResponse::success([
            'id' => (string) $link->public_id,
            'direction' => 'outgoing',
            'relation_type' => (string) $link->relation_type,
            'item' => $target instanceof KnowledgeItem ? [
                'id' => (string) $target->public_id,
                'title' => (string) $target->title,
            ] : null,
        ], status: 201);
    }
}
