<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Domains\Users\Models\User;
use Illuminate\Http\Request;
use LogicException;

trait InteractsWithApiUser
{
    protected function apiUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new LogicException('An authenticated EduConnect user is required.');
        }

        return $user;
    }
}
