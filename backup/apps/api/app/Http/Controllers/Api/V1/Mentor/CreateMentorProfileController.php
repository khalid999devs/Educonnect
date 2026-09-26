<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Actions\CreateMentorProfileAction;
use App\Http\Requests\Api\V1\Mentor\StoreMentorProfileRequest;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateMentorProfileController
{
    public function __invoke(StoreMentorProfileRequest $request, CreateMentorProfileAction $action): JsonResponse
    {
        $profile = $action->execute($request->authenticatedUser(), $request->profileData());

        return ApiResponse::success(MentorProfileResource::make($profile)->resolve($request), status: 201);
    }
}
