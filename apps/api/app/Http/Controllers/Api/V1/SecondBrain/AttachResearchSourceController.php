<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SecondBrain;

use App\Domains\SecondBrain\Actions\AttachResearchSourceAction;
use App\Http\Requests\Api\V1\SecondBrain\AttachResearchSourceRequest;
use App\Http\Resources\ResearchTopicResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class AttachResearchSourceController
{
    public function __invoke(AttachResearchSourceRequest $request, string $topic, AttachResearchSourceAction $attach): JsonResponse
    {
        $record = $attach->execute(
            $request->authenticatedUser(),
            $topic,
            $request->knowledgeItemId(),
            $request->readingStatus(),
        );

        return ApiResponse::success(ResearchTopicResource::make($record)->resolve($request), status: 201);
    }
}
