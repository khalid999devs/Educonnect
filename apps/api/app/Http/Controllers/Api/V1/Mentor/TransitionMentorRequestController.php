<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mentor;

use App\Domains\Mentor\Actions\TransitionMentorRequestAction;
use App\Http\Requests\Api\V1\Mentor\TransitionMentorRequestRequest;
use App\Http\Resources\MentorRequestResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class TransitionMentorRequestController
{
    public function __invoke(
        TransitionMentorRequestRequest $request,
        string $mentorRequest,
        TransitionMentorRequestAction $action,
    ): JsonResponse {
        $model = $action->execute(
            $request->authenticatedUser(),
            $mentorRequest,
            $request->action(),
            $request->responseNote(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(MentorRequestResource::make($model)->resolve($request));
    }
}
