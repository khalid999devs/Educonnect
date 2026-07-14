<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\RegisterUserAction;
use App\Domains\Auth\Exceptions\EmailAlreadyRegistered;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class RegisterController
{
    public function __invoke(RegisterRequest $request, RegisterUserAction $registerUser): JsonResponse
    {
        try {
            $user = $registerUser->execute($request->validated());
        } catch (EmailAlreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => ['The email has already been taken.'],
            ]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return ApiResponse::success([
            'user' => UserResource::make($user),
        ], status: 201);
    }
}
