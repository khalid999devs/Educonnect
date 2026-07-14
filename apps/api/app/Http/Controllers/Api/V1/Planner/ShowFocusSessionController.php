<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Queries\FindOwnedFocusSession;
use App\Domains\Users\Models\User;
use App\Http\Resources\FocusSessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

final class ShowFocusSessionController
{
    public function __invoke(
        Request $request,
        string $focus_session,
        FindOwnedFocusSession $sessions,
    ): JsonResponse {
        $resource = $sessions->execute($this->user($request), $focus_session);

        return ApiResponse::success(FocusSessionResource::make($resource)->resolve($request));
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
