<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

use Carbon\CarbonInterface;

final readonly class CompanySummary
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $bin,
        public bool $isActive,
        public bool $awaitingFirstAdmin,
        public CarbonInterface $createdAt,
    ) {}
}
