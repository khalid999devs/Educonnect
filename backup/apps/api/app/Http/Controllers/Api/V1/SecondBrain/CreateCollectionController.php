<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\CreateCollectionAction;
use App\Http\Requests\Api\V1\SecondBrain\StoreCollectionRequest;
use App\Http\Resources\CollectionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateCollectionController
{
    public function __invoke(StoreCollectionRequest $request, CreateCollectionAction $create): JsonResponse
    {
        $collection = $create->execute($request->authenticatedUser(), $request->collectionData());

        return ApiResponse::success(CollectionResource::make($collection)->resolve($request), status: 201);
    }
}
