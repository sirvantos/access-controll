<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OkResource;
use App\Modules\Tenancy\Actions\ClearSelectedCompanyAction;

final class ClearSelectedCompanyController extends Controller
{
    public function __invoke(ClearSelectedCompanyAction $action): OkResource
    {
        $action();

        return new OkResource(null);
    }
}
