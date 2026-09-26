<?php

declare(strict_types=1);

namespace App\Domains\Auth\Support;

use App\Domains\Auth\Rules\MaxPasswordBytes;
use Illuminate\Validation\Rules\Password;

final class PasswordRules
{
    public const MAXIMUM_BYTES = 72;

    public const MINIMUM_CHARACTERS = 8;

    /**
     * @return list<mixed>
     */
    public static function newPassword(): array
    {
        return [
            'required',
            'string',
            'confirmed',
            new MaxPasswordBytes(self::MAXIMUM_BYTES),
            Password::min(self::MINIMUM_CHARACTERS),
        ];
    }

    /**
     * @return list<mixed>
     */
    public static function currentPassword(): array
    {
        return [
            'required',
            'string',
            new MaxPasswordBytes(self::MAXIMUM_BYTES),
        ];
    }
}
