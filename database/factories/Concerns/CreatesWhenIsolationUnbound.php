<?php

declare(strict_types=1);

namespace Database\Factories\Concerns;

use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Support\CompanyContextStore;
use Illuminate\Database\Eloquent\Model;

trait CreatesWhenIsolationUnbound
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create($attributes = [], ?Model $parent = null)
    {
        $store = app(CompanyContextStore::class);

        if ($store->isCompanyBound() || $store->isWithoutIsolation()) {
            return parent::create($attributes, $parent);
        }

        return app(CompanyContext::class)->withoutIsolation(
            fn () => parent::create($attributes, $parent),
        );
    }
}
