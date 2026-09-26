<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use LogicException;

final class ResetUserPasswordAction
{
    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     */
    public function execute(array $credentials): bool
    {
        $resetUser = null;

        $status = DB::transaction(function () use ($credentials, &$resetUser): mixed {
            return Password::broker()->reset(
                $credentials,
                function (CanResetPassword $candidate, string $password) use (&$resetUser): void {
                    if (! $candidate instanceof User) {
                        throw new LogicException('The password broker returned an unsupported user type.');
                    }

                    $candidate->forceFill([
                        'password' => $password,
                        'remember_token' => Str::random(60),
                    ])->saveOrFail();

                    $this->deleteAccessCredentials($candidate);
                    $resetUser = $candidate;
                },
            );
        });

        if ($status !== Password::PASSWORD_RESET || ! $resetUser instanceof User) {
            return false;
        }

        event(new PasswordReset($resetUser));

        return true;
    }

    private function deleteAccessCredentials(User $user): void
    {
        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->delete();

        DB::table('personal_access_tokens')
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->getKey())
            ->delete();
    }
}
