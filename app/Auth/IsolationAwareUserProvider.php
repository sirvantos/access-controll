<?php

declare(strict_types=1);

namespace App\Auth;

use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher;

final class IsolationAwareUserProvider extends EloquentUserProvider
{
    public function __construct(
        Hasher $hasher,
        string $model,
        private readonly CompanyContext $companyContext,
    ) {
        parent::__construct($hasher, $model);
    }

    public function retrieveById($identifier)
    {
        return $this->companyContext->withoutIsolation(
            fn (): ?\Illuminate\Contracts\Auth\Authenticatable => parent::retrieveById($identifier),
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function retrieveByCredentials(array $credentials)
    {
        return $this->companyContext->withoutIsolation(
            fn (): ?\Illuminate\Contracts\Auth\Authenticatable => parent::retrieveByCredentials($credentials),
        );
    }
}
