<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Mentors;

use App\Domains\Mentor\Actions\SetMentorVerificationAction;
use App\Http\Requests\Api\V1\Admin\SetMentorVerificationRequest;
use App\Http\Resources\MentorProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class SetMentorVerificationController
{
    public function __invoke(
        SetMentorVerificationRequest $request,
        string $mentor,
        SetMentorVerificationAction $action,
    ): JsonResponse {
        $profile = $action->execute(
            $request->adminUser(),
            $mentor,
            $request->verificationState(),
            $request->reason(),
            $request->requestId(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(MentorProfileResource::make($profile)->resolve($request));
    }
}
