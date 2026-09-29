<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Tenancy\PublicApi\CompanyMediaAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveCompanyMediaKind
{
    public const string KIND_ATTRIBUTE = 'company_media_kind';

    public function __construct(private readonly CompanyMediaAccess $companyMediaAccess) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $publicId = $request->route('public_id');

        if (is_string($publicId)) {
            $request->attributes->set(
                self::KIND_ATTRIBUTE,
                $this->companyMediaAccess->kindForCurrentCompanyPublicId($publicId),
            );
        }

        return $next($request);
    }
}
