<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\PublicApi;

use Closure;

interface CompanyContext
{
    public const string COMPANY_CONTEXT_HEADER = 'X-Company-Context';

    public function companyId(): int;

    public function actorId(): ?int;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(int $companyId, Closure $callback): mixed;

    /**
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function withoutIsolation(Closure $callback): mixed;
}
