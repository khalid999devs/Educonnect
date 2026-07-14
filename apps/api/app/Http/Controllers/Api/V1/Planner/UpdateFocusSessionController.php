<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\UpdateFocusSessionAction;
use App\Http\Requests\Api\V1\Planner\UpdateFocusSessionRequest;
use App\Http\Resources\FocusSessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class UpdateFocusSessionController
{
    public function __invoke(
        UpdateFocusSessionRequest $request,
        string $focus_session,
        UpdateFocusSessionAction $update,
    ): JsonResponse {
        $resource = $update->execute(
            $request->authenticatedUser(),
            $focus_session,
            $request->focusData(),
            $request->expectedVersion(),
        );

        return ApiResponse::success(FocusSessionResource::make($resource)->resolve($request));
    }
}
