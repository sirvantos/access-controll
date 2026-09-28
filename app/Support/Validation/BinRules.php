<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class BinRules
{
    public const int COMPANY_BIN_LENGTH = 12;

    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'regex:/^(?:\d{'.self::COMPANY_BIN_LENGTH.'})?$/',
        ];
    }
}
