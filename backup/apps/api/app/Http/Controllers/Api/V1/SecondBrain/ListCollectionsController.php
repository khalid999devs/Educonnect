<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\ListOwnedCollections;
use App\Http\Requests\Api\V1\SecondBrain\ListCollectionsRequest;
use App\Http\Resources\CollectionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListCollectionsController
{
    public function __invoke(ListCollectionsRequest $request, ListOwnedCollections $collections): JsonResponse
    {
        $result = $collections->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->kind(),
            $request->sort(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            CollectionResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
