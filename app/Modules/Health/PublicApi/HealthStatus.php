<?php

declare(strict_types=1);

namespace App\Modules\Health\PublicApi;

final readonly class HealthStatus
{
    public function __construct(public string $status) {}
}
