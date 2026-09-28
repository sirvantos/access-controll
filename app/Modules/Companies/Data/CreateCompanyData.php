<?php

declare(strict_types=1);

namespace App\Modules\Companies\Data;

use Spatie\LaravelData\Data;

final class CreateCompanyData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $firstAdminEmail,
    ) {}
}
