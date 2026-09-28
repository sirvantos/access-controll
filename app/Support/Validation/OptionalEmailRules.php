<?php

declare(strict_types=1);

namespace App\Support\Validation;

final class OptionalEmailRules
{
    /**
     * @return list<string>
     */
    public static function rules(): array
    {
        return [
            'nullable',
            'string',
            'email',
            'max:'.EmailRules::EMAIL_MAX_LENGTH,
        ];
    }
}
