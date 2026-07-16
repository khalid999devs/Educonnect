<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Queries\FindOwnedIntakeItem;
use App\Http\Requests\Api\V1\Intake\EmptyIntakeRequest;
use App\Http\Resources\IntakeSuggestionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListIntakeSuggestionsController
{
    public function __invoke(EmptyIntakeRequest $request, string $item, FindOwnedIntakeItem $items): JsonResponse
    {
        $ownedItem = $items->execute($request->authenticatedUser(), $item);
        $suggestions = $ownedItem->suggestions()
            ->with(['createdTask', 'createdResource'])
            ->get();

        return ApiResponse::success(
            IntakeSuggestionResource::collection($suggestions)->resolve($request),
        );
    }
}
