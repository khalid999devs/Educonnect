<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\ResetUserPasswordAction;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class ResetPasswordController
{
    public function __invoke(
        ResetPasswordRequest $request,
        ResetUserPasswordAction $resetUserPassword,
    ): JsonResponse {
        if (! $resetUserPassword->execute($request->credentials())) {
            throw ValidationException::withMessages([
                'token' => ['The password reset link is invalid or expired.'],
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
        Auth::shouldUse('web');

        return ApiResponse::success([
            'message' => 'Your password has been reset.',
        ]);
    }
}
