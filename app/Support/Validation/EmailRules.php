<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class EmailRules
{
    public const int EMAIL_MAX_LENGTH = 255;

    public static function rules(): string
    {
        return 'required|string|email|max:'.self::EMAIL_MAX_LENGTH;
    }
}
