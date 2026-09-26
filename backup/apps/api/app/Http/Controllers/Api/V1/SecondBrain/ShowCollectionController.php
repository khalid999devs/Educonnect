<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\FindOwnedCollection;
use App\Domains\Users\Models\User;
use App\Http\Resources\CollectionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowCollectionController
{
    public function __invoke(Request $request, string $collection, FindOwnedCollection $collections): JsonResponse
    {
        $record = $collections->execute($this->user($request), $collection)->loadCount('knowledgeItems');

        return ApiResponse::success(CollectionResource::make($record)->resolve($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
