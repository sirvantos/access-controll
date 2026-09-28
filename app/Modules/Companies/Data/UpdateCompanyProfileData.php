<?php

declare(strict_types=1);

namespace App\Modules\Companies\Data;

use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

final class UpdateCompanyProfileData extends Data
{
    public function __construct(
        public readonly Optional|Stringable $name,
        public readonly Optional|Stringable $timeZone,
        public readonly Optional|Stringable|null $bin,
        public readonly Optional|Stringable|null $contactPerson,
        public readonly Optional|Stringable|null $phone,
        public readonly Optional|Stringable|null $email,
    ) {}
}
