<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Domains\Auth\Actions\AuthenticateAdminAction;
use App\Http\Middleware\EnsureAdminSessionPasswordIsCurrent;
use App\Http\Requests\Api\V1\Admin\Auth\AdminLoginRequest;
use App\Http\Resources\AdminSessionResource;
use App\Support\ApiResponse;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LogicException;

final class AdminLoginController
{
    public function __invoke(
        AdminLoginRequest $request,
        AuthenticateAdminAction $authenticateAdmin,
    ): JsonResponse {
        $credentials = $request->validated();

        $this->sessionGuard('web')->logoutCurrentDevice();
        $this->sessionGuard('admin')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Auth::forgetGuards();
        Auth::shouldUse('web');

        $user = $authenticateAdmin->execute($credentials['email'], $credentials['password']);

        if ($user === null) {
            Auth::forgetGuards();
            Auth::shouldUse('web');

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $request->session()->regenerate();
        $guard = $this->sessionGuard('admin');

        $request->session()->put(
            EnsureAdminSessionPasswordIsCurrent::PASSWORD_HASH_KEY,
            $guard->hashPasswordForCookie($user->getAuthPassword()),
        );
        Auth::shouldUse('admin');

        return ApiResponse::success(AdminSessionResource::make($user));
    }

    private function sessionGuard(string $name): SessionGuard
    {
        $guard = Auth::guard($name);

        if (! $guard instanceof SessionGuard) {
            throw new LogicException("The {$name} authentication guard must use sessions.");
        }

        return $guard;
    }
}
