<?php

declare(strict_types=1);

namespace App\Modules\Companies\Data;

use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;

final class CreateCompanyData extends Data
{
    public function __construct(
        public readonly Stringable $name,
        public readonly Stringable $firstAdminEmail,
        public readonly Stringable $timeZone,
        public readonly ?Stringable $bin = null,
        public readonly ?Stringable $contactPerson = null,
        public readonly ?Stringable $phone = null,
        public readonly ?Stringable $email = null,
    ) {}
}
