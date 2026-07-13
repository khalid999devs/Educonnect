<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\RegisterUserAction;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

final class RegisterController
{
    public function __invoke(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        $user = $registerUser->execute($request->validated());

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::success([
            'user' => UserResource::make($user),
        ], status: 201);
    }
}
