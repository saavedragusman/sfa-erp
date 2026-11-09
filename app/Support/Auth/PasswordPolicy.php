<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * Single definition of the password rule and of the hint shown next to it.
 * Registered as Password::defaults() in AppServiceProvider, so every Password::default() uses it.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 8;

    public static function rule(): Password
    {
        return Password::min(self::MIN_LENGTH);
    }

    public static function hint(): string
    {
        return 'Mínimo '.self::MIN_LENGTH.' caracteres.';
    }
}
