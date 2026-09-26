<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;
use Illuminate\Auth\Events\Verified;

final class VerifyUserEmailAction
{
    public function execute(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }
    }
}
