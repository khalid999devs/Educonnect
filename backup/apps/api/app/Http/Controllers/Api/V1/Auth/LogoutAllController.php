<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Domains\Auth\Actions\LogoutAllSessionsAction;
use App\Http\Requests\Api\V1\Auth\LogoutAllRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class LogoutAllController
{
    public function __invoke(
        LogoutAllRequest $request,
        LogoutAllSessionsAction $logoutAllSessions,
    ): JsonResponse {
        if (! $logoutAllSessions->execute($request->authenticatedUser(), $request->password())) {
            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
        Auth::shouldUse('web');

        return ApiResponse::success(null);
    }
}
