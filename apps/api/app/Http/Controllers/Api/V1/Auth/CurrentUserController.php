<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentUserController
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'user' => UserResource::make($request->user()),
        ]);
    }
}
