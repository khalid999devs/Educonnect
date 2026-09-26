<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Planner;

use App\Domains\Planner\Actions\CreateFocusSessionAction;
use App\Http\Requests\Api\V1\Planner\StoreFocusSessionRequest;
use App\Http\Resources\FocusSessionResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

final class CreateFocusSessionController
{
    public function __invoke(
        StoreFocusSessionRequest $request,
        CreateFocusSessionAction $create,
    ): JsonResponse {
        $session = $create->execute($request->authenticatedUser(), $request->focusData());

        return ApiResponse::success(FocusSessionResource::make($session)->resolve($request), status: 201);
    }
}
