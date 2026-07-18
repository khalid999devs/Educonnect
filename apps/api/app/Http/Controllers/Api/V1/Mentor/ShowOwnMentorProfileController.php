<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Queries\FindOwnMentorProfile;
use App\Http\Controllers\Api\V1\Concerns\InteractsWithApiUser;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowOwnMentorProfileController
{
    use InteractsWithApiUser;

    public function __invoke(Request $request, FindOwnMentorProfile $profiles): JsonResponse
    {
        $profile = $profiles->execute($this->apiUser($request));

        return ApiResponse::success(MentorProfileResource::make($profile)->resolve($request));
    }
}
