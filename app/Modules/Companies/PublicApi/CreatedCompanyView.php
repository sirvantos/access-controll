<?php

declare(strict_types=1);

namespace App\Modules\Companies\PublicApi;

use Carbon\CarbonInterface;

final readonly class CreatedCompanyView
{
    public function __construct(
        public int $id,
        public string $name,
        public string $timeZone,
        public ?string $bin,
        public ?string $contactPerson,
        public ?string $phone,
        public ?string $email,
        public bool $isActive,
        public bool $awaitingFirstAdmin,
        public CarbonInterface $createdAt,
        public WorkingDaySettingsView $workingDaySettings,
    ) {}
}
