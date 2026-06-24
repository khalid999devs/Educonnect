<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\AuthenticateUserAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, AuthenticateUserAction $authenticateUser): JsonResponse
    {
        $credentials = $request->validated();
        $user = $authenticateUser->execute($credentials['email'], $credentials['password']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();

        return ApiResponse::success([
            'user' => UserResource::make($user),
        ]);
    }
}
