<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mentors;

use App\Domains\Mentor\Queries\ListMentorProfilesForAdmin;
use App\Http\Requests\Api\V1\Admin\ListAdminMentorsRequest;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class ListAdminMentorsController
{
    public function __invoke(ListAdminMentorsRequest $request, ListMentorProfilesForAdmin $mentors): JsonResponse
    {
        $result = $mentors->execute(
            $request->search(),
            $request->verificationState(),
            $request->perPage(),
        );

        return ApiResponse::collection(
            MentorProfileResource::collection($result->paginator->items())->resolve($request),
            $result->paginator,
        );
    }
}
