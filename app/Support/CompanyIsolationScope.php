<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class CompanyIsolationScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $store = app(CompanyContextStore::class);

        if ($store->isWithoutIsolation()) {
            return;
        }

        if (! $store->isCompanyBound()) {
            $store->invokeUnboundHandler();

            return;
        }

        if (! method_exists($model, 'companyIsolationColumn')) {
            return;
        }

        $column = $model->companyIsolationColumn();
        $qualified = $model->getTable().'.'.$column;

        $builder->where($qualified, '=', $store->peekCompanyId());
    }
}
