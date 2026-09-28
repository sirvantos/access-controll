<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Modules\Companies\Actions\ReactivateCompanyAction;

final class ReactivateCompanyController extends Controller
{
    public function __invoke(int $company, ReactivateCompanyAction $action): CompanyResource
    {
        return new CompanyResource($action($company));
    }
}
