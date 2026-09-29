<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    public function companyIsolationColumn(): string
    {
        return 'company_id';
    }

    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyIsolationScope);

        static::creating(function (Model $model): void {
            self::guardCompanyIsolationOnCreating($model);
        });

        static::saving(function (Model $model): void {
            self::guardCompanyIsolationOnSaving($model);
        });

        static::created(function (Model $model): void {
            self::recordCompanyMutation($model);
        });

        static::updated(function (Model $model): void {
            self::recordCompanyMutation($model);
        });
    }

    private static function guardCompanyIsolationOnCreating(Model $model): void
    {
        $store = app(CompanyContextStore::class);

        if ($store->isWithoutIsolation()) {
            return;
        }

        if (! $store->isCompanyBound()) {
            $store->invokeUnboundHandler();

            return;
        }

        if (! method_exists($model, 'companyIsolationColumn')
            || $model->companyIsolationColumn() !== 'company_id') {
            return;
        }

        if (method_exists($model, 'allowsNullCompanyOwner')
            && $model->allowsNullCompanyOwner()
            && $model->getAttribute('company_id') === null) {
            return;
        }

        $model->setAttribute('company_id', $store->peekCompanyId());
    }

    private static function guardCompanyIsolationOnSaving(Model $model): void
    {
        $store = app(CompanyContextStore::class);

        if ($store->isWithoutIsolation()) {
            return;
        }

        $scopesByCompanyId = method_exists($model, 'companyIsolationColumn')
            && $model->companyIsolationColumn() === 'company_id';

        if (! $scopesByCompanyId) {
            if (! $store->isCompanyBound()) {
                $store->invokeUnboundHandler();
            }

            return;
        }

        if (
            $model->exists
            && method_exists($model, 'allowsNullCompanyOwner')
            && $model->allowsNullCompanyOwner()
            && $model->getAttribute('company_id') === null
            && ! $model->isDirty('company_id')
        ) {
            return;
        }

        if (! $store->isCompanyBound()) {
            $store->invokeUnboundHandler();

            return;
        }

        if ($model->exists && $model->isDirty('company_id')) {
            $originalCompanyId = $model->getRawOriginal('company_id');

            if ($originalCompanyId !== null) {
                $model->setAttribute('company_id', $originalCompanyId);
            } elseif ($store->isCompanyBound()) {
                $model->setAttribute('company_id', $store->requireCompanyId());
            }
        }

        if ($model->exists && $model->getAttribute('company_id') !== null) {
            return;
        }

        if ($model->getAttribute('company_id') !== null) {
            return;
        }

        if (method_exists($model, 'allowsNullCompanyOwner') && $model->allowsNullCompanyOwner()) {
            return;
        }

        $store->invokeUnboundHandler();
    }

    private static function recordCompanyMutation(Model $model): void
    {
        if (! app()->bound(CompanyMutationRecorder::class)) {
            return;
        }

        app(CompanyMutationRecorder::class)->record($model);
    }
}
