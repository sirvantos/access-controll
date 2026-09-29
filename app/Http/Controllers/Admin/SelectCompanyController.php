<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureCompanyContext;
use App\Http\Requests\SelectCompanyRequest;
use App\Http\Resources\SelectedCompanyResource;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\Actions\SelectCompanyAction;
use Illuminate\Auth\AuthenticationException;

final class SelectCompanyController extends Controller
{
    public function __invoke(SelectCompanyRequest $request, SelectCompanyAction $action): SelectedCompanyResource
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $companyId = $request->companyId();
        $selected = $action($actor, $companyId);
        $allowList = $request->session()->get(EnsureCompanyContext::SESSION_ALLOW_LIST_KEY, []);

        if (! is_array($allowList)) {
            $allowList = [];
        }

        $allowList[] = $companyId;

        $ids = [];

        foreach ($allowList as $id) {
            if (is_int($id) || is_string($id)) {
                $ids[] = (int) $id;
            }
        }

        $request->session()->put(
            EnsureCompanyContext::SESSION_ALLOW_LIST_KEY,
            array_values(array_unique($ids)),
        );

        return new SelectedCompanyResource($selected);
    }
}
