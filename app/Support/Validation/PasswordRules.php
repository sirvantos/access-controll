<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class PasswordRules
{
    public const int PASSWORD_MIN_LENGTH = 8;

    public const int PASSWORD_MAX_LENGTH = 128;

    public static function rules(): string
    {
        return 'required|string|min:'.self::PASSWORD_MIN_LENGTH.'|max:'.self::PASSWORD_MAX_LENGTH;
    }
}
