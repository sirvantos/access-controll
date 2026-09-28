<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TimeZoneIdentifiersResource;
use App\Modules\Companies\Actions\ListTimeZonesAction;

final class ListTimeZonesController extends Controller
{
    public function __invoke(ListTimeZonesAction $action): TimeZoneIdentifiersResource
    {
        return new TimeZoneIdentifiersResource($action());
    }
}
