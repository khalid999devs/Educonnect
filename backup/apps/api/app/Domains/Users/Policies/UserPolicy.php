<?php

declare(strict_types=1);

namespace App\Domains\Users\Policies;

use App\Domains\Users\Models\User;

final class UserPolicy
{
    public function view(User $user, User $subject): bool
    {
        return $user->is($subject);
    }
}
