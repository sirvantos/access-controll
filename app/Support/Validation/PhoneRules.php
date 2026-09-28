<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class PhoneRules
{
    public const int COMPANY_PHONE_MAX_LENGTH = 32;

    public const int COMPANY_PHONE_MIN_DIGITS = 10;

    public const int COMPANY_PHONE_MAX_DIGITS = 15;

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'string',
            'max:'.self::COMPANY_PHONE_MAX_LENGTH,
            'regex:'.self::pattern(),
        ];
    }

    public static function pattern(): string
    {
        return '/^(?:\+?(?:[ \-()]*\d){'.self::COMPANY_PHONE_MIN_DIGITS.','.self::COMPANY_PHONE_MAX_DIGITS.'}[ \-()]*)?$/';
    }
}
