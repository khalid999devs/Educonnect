<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;

final class AuthenticateUserAction
{
    public function execute(string $email, string $password): ?User
    {
        if (! Auth::guard('web')->attempt([
            'email' => $email,
            'password' => $password,
        ])) {
            return null;
        }

        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            Auth::guard('web')->logout();

            return null;
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $user;
    }
}
