<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Queries\FindOwnedIntakeItem;
use App\Domains\Intake\Queries\ReadIntakeExtraction;
use App\Http\Requests\Api\V1\Intake\ShowIntakeExtractionRequest;
use App\Http\Resources\IntakeExtractionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ShowIntakeExtractionController
{
    public function __invoke(
        ShowIntakeExtractionRequest $request,
        string $item,
        FindOwnedIntakeItem $items,
        ReadIntakeExtraction $extraction,
    ): JsonResponse {
        $user = $request->authenticatedUser();
        $ownedItem = $items->execute($user, $item);
        $window = $extraction->execute($user, $ownedItem, $request->offset(), $request->limit());

        return ApiResponse::success(IntakeExtractionResource::make($window)->resolve($request));
    }
}
