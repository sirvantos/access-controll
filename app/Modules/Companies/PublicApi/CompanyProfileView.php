<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

final readonly class CompanyProfileView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $timeZone,
        public ?string $bin,
        public ?string $contactPerson,
        public ?string $phone,
        public ?string $email,
    ) {}
}
