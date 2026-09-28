<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;

final class PasswordResetData extends Data
{
    public function __construct(
        public readonly Stringable $email,
        public readonly Stringable $token,
        public readonly Stringable $password,
    ) {}
}
