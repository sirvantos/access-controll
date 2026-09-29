<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\CompanyNotSelectedException;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Identity\PublicApi\Role;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Support\CompanyContextStore;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCompanyContext
{
    public const string SESSION_ALLOW_LIST_KEY = 'tenancy.selected_company_ids';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        throw_unless($actor instanceof Actor, AuthenticationException::class);

        $store = app(CompanyContextStore::class);
        $store->setActorId($actor->actorId());
        $store->setActorRole($actor->actorRole()->value);

        $role = $actor->actorRole();

        if ($role === Role::CompanyAdmin || $role === Role::Viewer) {
            $companyId = $actor->actorCompanyId();

            throw_unless(is_int($companyId), InvalidArgumentException::class);

            $store->bindCompany($companyId);

            return $next($request);
        }

        $store->bindCompany($this->selectedCompanyId($request));

        return $next($request);
    }

    private function selectedCompanyId(Request $request): int
    {
        $raw = $request->headers->get(CompanyContext::COMPANY_CONTEXT_HEADER);
        $companyId = filter_var($raw, FILTER_VALIDATE_INT);

        throw_unless(is_int($companyId) && $companyId > 0, CompanyNotSelectedException::class);
        throw_unless(in_array($companyId, $this->allowList($request), true), CompanyNotSelectedException::class);

        return $companyId;
    }

    /**
     * @return list<int>
     */
    private function allowList(Request $request): array
    {
        $allowList = $request->session()->get(self::SESSION_ALLOW_LIST_KEY, []);

        if (! is_array($allowList)) {
            return [];
        }

        $ids = [];

        foreach ($allowList as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
