<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Exceptions\EmailAlreadyRegistered;
use App\Domains\Users\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        try {
            $user = User::query()->create($attributes);
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === 'users_email_unique' || in_array('email', $exception->columns, true)) {
                throw new EmailAlreadyRegistered;
            }

            throw $exception;
        }

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
