<?php

declare(strict_types=1);

namespace App\Domains\Auth\Actions;

use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class LogoutAllSessionsAction
{
    public function execute(User $user, string $password): bool
    {
        if (! Hash::check($password, $user->getAuthPassword())) {
            return false;
        }

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'remember_token' => Str::random(60),
            ])->saveOrFail();

            DB::table('sessions')
                ->where('user_id', $user->getKey())
                ->delete();

            DB::table('personal_access_tokens')
                ->where('tokenable_type', $user->getMorphClass())
                ->where('tokenable_id', $user->getKey())
                ->delete();
        });

        return true;
    }
}
