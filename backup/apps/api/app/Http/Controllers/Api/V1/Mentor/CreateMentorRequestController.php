<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Actions\CreateMentorRequestAction;
use App\Http\Requests\Api\V1\Mentor\StoreMentorRequestRequest;
use App\Http\Resources\MentorRequestResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateMentorRequestController
{
    public function __invoke(StoreMentorRequestRequest $request, string $mentor, CreateMentorRequestAction $action): JsonResponse
    {
        $mentorRequest = $action->execute(
            $request->authenticatedUser(),
            $mentor,
            $request->subject(),
            $request->message(),
            $request->contextCourseId(),
        );

        return ApiResponse::success(MentorRequestResource::make($mentorRequest)->resolve($request), status: 201);
    }
}
