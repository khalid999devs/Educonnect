<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Domains\Users\Models\User;
use App\Http\Middleware\EnsureAdminReauthenticated;
use App\Http\Requests\Api\V1\Admin\Auth\AdminReauthRequest;
use App\Support\ApiResponse;
use App\Support\RequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Establishes a fresh step-up re-authentication grant for high-risk admin
 * actions. The administrator is already authenticated on the admin guard; this
 * verifies the current password again and records a short-lived, session-bound
 * confirmation that EnsureAdminReauthenticated consumes as a time window.
 */
final class AdminReauthController
{
    public function __invoke(AdminReauthRequest $request): JsonResponse
    {
        $admin = $request->user('admin');

        if (! $admin instanceof User) {
            throw new AuthenticationException('Unauthenticated.', ['admin']);
        }

        $requestId = RequestId::getOrCreate($request);

        if (! Hash::check((string) $request->validated('password'), $admin->getAuthPassword())) {
            Log::warning('admin.reauth.failed', [
                'actor' => $admin->public_id,
                'request_id' => $requestId,
                'ip' => $request->ip(),
            ]);

            throw ValidationException::withMessages([
                'password' => ['The provided password is incorrect.'],
            ]);
        }

        $timeout = (int) config('auth.admin_reauth_timeout', 300);
        $now = Carbon::now();

        $request->session()->put(EnsureAdminReauthenticated::CONFIRMED_AT_KEY, $now->getTimestamp());

        Log::info('admin.reauth.confirmed', [
            'actor' => $admin->public_id,
            'request_id' => $requestId,
            'ip' => $request->ip(),
        ]);

        return ApiResponse::success([
            'reauthenticated_until' => $now->clone()->addSeconds($timeout)->toIso8601String(),
        ]);
    }
}
