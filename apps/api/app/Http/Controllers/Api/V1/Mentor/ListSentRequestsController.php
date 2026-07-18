<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Queries\ListSentRequests;
use App\Http\Requests\Api\V1\Mentor\ListMentorRequestsRequest;
use App\Http\Resources\MentorRequestResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListSentRequestsController
{
    public function __invoke(ListMentorRequestsRequest $request, ListSentRequests $requests): JsonResponse
    {
        $result = $requests->execute($request->authenticatedUser(), $request->perPage());

        return ApiResponse::collection(
            MentorRequestResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
