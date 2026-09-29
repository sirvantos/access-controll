<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

final readonly class SelectedCompanyView
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
