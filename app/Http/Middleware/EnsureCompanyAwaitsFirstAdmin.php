<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class EnsureCompanyAwaitsFirstAdmin
{
    public function __construct(
        private CompanyDirectory $companies,
        private FirstAdminInvitations $invitations,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $companyId = $this->companyId($request);

        throw_unless($this->companies->exists($companyId), NotFoundHttpException::class);
        throw_unless($this->invitations->isAwaitingFirstAdmin($companyId), AuthorizationException::class);

        return $next($request);
    }

    private function companyId(Request $request): int
    {
        $companyId = $request->route('company');

        throw_unless(is_string($companyId) && ctype_digit($companyId), NotFoundHttpException::class);

        return (int) $companyId;
    }
}
