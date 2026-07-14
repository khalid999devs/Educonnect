<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use Illuminate\Support\Facades\Password;

final class SendPasswordResetLinkAction
{
    public function execute(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }
}
