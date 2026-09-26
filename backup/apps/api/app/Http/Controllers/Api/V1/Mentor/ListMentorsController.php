<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Queries\ListMentorProfiles;
use App\Http\Requests\Api\V1\Mentor\ListMentorsRequest;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListMentorsController
{
    public function __invoke(ListMentorsRequest $request, ListMentorProfiles $mentors): JsonResponse
    {
        $result = $mentors->execute(
            $request->authenticatedUser(),
            $request->search(),
            $request->expertise(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            MentorProfileResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
            ['summary' => $result->summary],
        );
    }
}
