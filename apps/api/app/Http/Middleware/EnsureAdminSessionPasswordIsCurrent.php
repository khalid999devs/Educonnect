<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminSessionPasswordIsCurrent
{
    public const PASSWORD_HASH_KEY = 'password_hash_admin';

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');
        $user = $request->user('admin');

        if (! $guard instanceof SessionGuard || ! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.', ['admin']);
        }

        $expectedHash = $guard->hashPasswordForCookie($user->getAuthPassword());
        $sessionHash = $request->session()->get(self::PASSWORD_HASH_KEY);

        if (! is_string($sessionHash) || ! hash_equals($expectedHash, $sessionHash)) {
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', ['admin']);
        }

        $response = $next($request);

        if ($guard->check()) {
            $request->session()->put(self::PASSWORD_HASH_KEY, $expectedHash);
        }

        return $response;
    }
}
