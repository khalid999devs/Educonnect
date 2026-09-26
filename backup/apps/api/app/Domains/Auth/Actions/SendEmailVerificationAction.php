<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;

final class SendEmailVerificationAction
{
    public function execute(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }
    }
}
