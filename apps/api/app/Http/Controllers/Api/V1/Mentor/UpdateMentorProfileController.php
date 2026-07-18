<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Actions\UpdateMentorProfileAction;
use App\Http\Requests\Api\V1\Mentor\UpdateMentorProfileRequest;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateMentorProfileController
{
    public function __invoke(UpdateMentorProfileRequest $request, UpdateMentorProfileAction $action): JsonResponse
    {
        $profile = $action->execute($request->authenticatedUser(), $request->profileData(), $request->expectedVersion());

        return ApiResponse::success(MentorProfileResource::make($profile)->resolve($request));
    }
}
