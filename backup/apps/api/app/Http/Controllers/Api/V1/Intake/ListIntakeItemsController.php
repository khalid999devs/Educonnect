<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Intake;

use App\Domains\Intake\Queries\ListOwnedIntakeItems;
use App\Http\Requests\Api\V1\Intake\ListIntakeItemsRequest;
use App\Http\Resources\IntakeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListIntakeItemsController
{
    public function __invoke(ListIntakeItemsRequest $request, ListOwnedIntakeItems $items): JsonResponse
    {
        $paginator = $items->execute(
            $request->authenticatedUser(),
            $request->state(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            IntakeItemResource::collection($paginator->items())->resolve($request),
            $paginator,
        );
    }
}
