<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Authorization\Enums\CapabilityKey;
use App\Domains\Users\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use LogicException;

final class AuthenticateAdminAction
{
    public function execute(string $email, string $password): ?User
    {
        $guard = Auth::guard('admin');

        if (! $guard instanceof SessionGuard) {
            throw new LogicException('The admin authentication guard must use sessions.');
        }

        $authenticated = $guard->attemptWhen(
            ['email' => $email, 'password' => $password],
            static fn (Authenticatable $candidate): bool => $candidate instanceof User
                && $candidate->hasVerifiedEmail()
                && ! $candidate->isSuspended()
                && $candidate->hasCapability(CapabilityKey::AdminAccess),
        );

        if (! $authenticated) {
            return null;
        }

        $user = $guard->user();

        if (! $user instanceof User) {
            $guard->logoutCurrentDevice();

            return null;
        }

        $user->forceFill(['last_login_at' => now()])->saveOrFail();

        return $user;
    }
}
