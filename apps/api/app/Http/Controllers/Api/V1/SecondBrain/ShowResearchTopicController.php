<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Queries\FindOwnedResearchTopic;
use App\Domains\Users\Models\User;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowResearchTopicController
{
    public function __invoke(Request $request, string $topic, FindOwnedResearchTopic $topics): JsonResponse
    {
        $record = $topics->execute($this->user($request), $topic, withSources: true);

        return ApiResponse::success(ResearchTopicResource::make($record)->resolve($request));
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
