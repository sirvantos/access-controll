<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use Spatie\LaravelData\Data;

final class PasswordResetData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly string $token,
        public readonly string $password,
    ) {}
}
