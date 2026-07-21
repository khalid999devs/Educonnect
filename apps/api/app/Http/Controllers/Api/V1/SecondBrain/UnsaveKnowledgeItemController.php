<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UnsaveKnowledgeItemAction;
use App\Http\Requests\Api\V1\SecondBrain\EmptyBrainRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Response;

final class UnsaveKnowledgeItemController
{
    public function __invoke(
        EmptyBrainRequest $request,
        string $item,
        UnsaveKnowledgeItemAction $unsave,
    ): Response {
        $unsave->execute($request->authenticatedUser(), $item);

        return ApiResponse::noContent();
    }
}
