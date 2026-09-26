<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\UpdateCollectionAction;
use App\Http\Requests\Api\V1\SecondBrain\UpdateCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateCollectionController
{
    public function __invoke(UpdateCollectionRequest $request, string $collection, UpdateCollectionAction $update): JsonResponse
    {
        $record = $update->execute(
            $request->authenticatedUser(),
            $collection,
            $request->collectionData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(CollectionResource::make($record)->resolve($request));
    }
}
