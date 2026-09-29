<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Support\CompanyContextStore;
use Closure;

final class CompanyContextService implements CompanyContext
{
    public function __construct(private readonly CompanyContextStore $store)
    {
        $this->store->registerUnboundHandler(fn (): never => throw new CompanyContextRequiredException);
    }

    public function companyId(): int
    {
        return $this->store->requireCompanyId();
    }

    public function actorId(): ?int
    {
        return $this->store->actorId();
    }

    public function run(int $companyId, Closure $callback): mixed
    {
        $wasBound = $this->store->isCompanyBound();
        $previousCompanyId = $this->store->peekCompanyId();

        $this->store->bindCompany($companyId);

        try {
            return $callback();
        } finally {
            if ($wasBound && $previousCompanyId !== null) {
                $this->store->bindCompany($previousCompanyId);
            } else {
                $this->store->unbindCompany();
            }
        }
    }

    public function withoutIsolation(Closure $callback): mixed
    {
        $this->store->enterWithoutIsolation();

        try {
            return $callback();
        } finally {
            $this->store->leaveWithoutIsolation();
        }
    }
}
