<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Modules\Companies\Actions\DeactivateCompanyAction;

final class DeactivateCompanyController extends Controller
{
    public function __invoke(int $company, DeactivateCompanyAction $action): CompanyResource
    {
        return new CompanyResource($action($company));
    }
}
