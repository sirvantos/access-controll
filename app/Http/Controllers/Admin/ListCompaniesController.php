<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListCompaniesRequest;
use App\Http\Resources\CompanyResource;
use App\Modules\Companies\Actions\ListCompaniesAction;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCompaniesController extends Controller
{
    public function __invoke(ListCompaniesRequest $request, ListCompaniesAction $action): AnonymousResourceCollection
    {
        return CompanyResource::collection($action($request->searchTerm(), $request->integer('page', 1)));
    }
}
