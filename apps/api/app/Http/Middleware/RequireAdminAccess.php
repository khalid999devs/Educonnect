<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireAdminAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('admin');

        if (! $user instanceof User
            || ! $user->hasVerifiedEmail()
            || $user->isSuspended()
            || ! $user->hasCapability(CapabilityKey::AdminAccess)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
