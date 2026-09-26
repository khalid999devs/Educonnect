<?php

namespace App\Domains\Auth\Actions;

use App\Domains\Auth\Exceptions\EmailAlreadyRegistered;
use App\Domains\Authorization\Enums\RoleKey;
use App\Domains\Authorization\Models\Role;
use App\Domains\Users\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RegisterUserAction
{
    /**
     * @param  array{name: string, email: string, password: string}  $attributes
     */
    public function execute(array $attributes): User
    {
        try {
            $user = DB::transaction(function () use ($attributes): User {
                $user = User::query()->create($attributes);
                $studentRoleId = Role::query()->where('key', RoleKey::Student->value)->value('id');

                if (! is_int($studentRoleId)) {
                    throw new RuntimeException('The canonical student role is unavailable.');
                }

                $user->roles()->attach($studentRoleId, ['assigned_at' => now()]);

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($exception->index === 'users_email_unique' || in_array('email', $exception->columns, true)) {
                throw new EmailAlreadyRegistered;
            }

            throw $exception;
        }

        if (config('auth.verification.required')) {
            $user->sendEmailVerificationNotification();
        } else {
            $user->markEmailAsVerified();
        }

        return $user;
    }
}
