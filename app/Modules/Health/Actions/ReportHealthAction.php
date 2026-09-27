<?php

declare(strict_types=1);

namespace App\Modules\Health\Actions;

use App\Modules\Health\PublicApi\HealthStatus;

final class ReportHealthAction
{
    public function __invoke(): HealthStatus
    {
        return new HealthStatus('ok');
    }
}
