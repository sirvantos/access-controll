<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\HealthResource;
use App\Modules\Health\Actions\ReportHealthAction;

final class HealthController extends Controller
{
    public function __invoke(ReportHealthAction $reportHealth): HealthResource
    {
        return new HealthResource($reportHealth());
    }
}
