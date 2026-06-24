<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;

final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
