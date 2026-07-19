<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Users\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-route capability gate for the admin surface. The admin app authenticates
 * on the `admin` guard, so - unlike the student `can:` middleware, which reads
 * the default guard - capability checks here resolve the actor explicitly
 * through it. Layered after RequireAdminAccess (which already establishes a
 * verified admin-access holder), this narrows a route to a capability. Multiple
 * capabilities are any-of, which covers gates such as "moderate" that any of a
 * scoped- or global-moderation holder may pass.
 */
final class EnsureAdminCapability
{
    public function handle(Request $request, Closure $next, string ...$capabilities): Response
    {
        $user = $request->user('admin');

        if (! $user instanceof User) {
            throw new AuthorizationException;
        }

        foreach ($capabilities as $capability) {
            if ($user->hasCapability($capability)) {
                return $next($request);
            }
        }

        throw new AuthorizationException;
    }
}
