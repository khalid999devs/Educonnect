<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireVerifiedEmail
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException('Unauthenticated.', ['sanctum']);
        }

        if (! $user->hasVerifiedEmail()) {
            throw new AuthorizationException('Email verification is required.');
        }

        return $next($request);
    }
}
