<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

final readonly class CompanyUserView
{
    public function __construct(
        public int $id,
        public string $email,
        public Role $role,
        public bool $isActive,
    ) {}
}
