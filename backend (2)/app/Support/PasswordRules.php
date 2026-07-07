<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    public const MIN_LENGTH = 8;

    public static function defaults(): Password
    {
        return Password::min(self::MIN_LENGTH)
            ->letters()
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}
