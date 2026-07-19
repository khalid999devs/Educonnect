<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Auth\Exceptions\AdminReauthenticationRequired;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up re-authentication gate for the highest-risk admin actions (account
 * suspension/reactivation, role assignment, shared demo-data seeding). Layered
 * after the capability gate, it requires a password confirmation recorded in
 * the session within a short, fixed TTL - a compromised or unattended live
 * session cannot perform these actions without the current password. The grant
 * is a time window (not one-time), so a short burst of related actions does not
 * re-prompt on every click; it is never refreshed by use, so it always expires.
 */
final class EnsureAdminReauthenticated
{
    public const CONFIRMED_AT_KEY = 'admin_reauthenticated_at';

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get(self::CONFIRMED_AT_KEY);
        $timeout = (int) config('auth.admin_reauth_timeout', 300);
        $now = Carbon::now()->getTimestamp();

        // Reject a missing grant, an expired grant, or a future timestamp (a
        // clock anomaly must fail closed rather than extend the window).
        if (! is_int($confirmedAt)
            || $confirmedAt > $now
            || ($now - $confirmedAt) > $timeout) {
            throw new AdminReauthenticationRequired;
        }

        return $next($request);
    }
}
