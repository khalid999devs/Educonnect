<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\FindOwnedKnowledgeItem;
use App\Domains\Users\Models\User;
use App\Http\Resources\KnowledgeItemResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowKnowledgeItemController
{
    public function __invoke(Request $request, string $item, FindOwnedKnowledgeItem $items): JsonResponse
    {
        $record = $items->execute($this->user($request), $item, withDetail: true);

        return ApiResponse::success(KnowledgeItemResource::make($record)->resolve($request));
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
