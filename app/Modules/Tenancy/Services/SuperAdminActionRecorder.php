<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\Identity\PublicApi\Role;
use App\Modules\Tenancy\Data\SuperAdminActionType;
use App\Modules\Tenancy\Models\SuperAdminActionRecord;
use App\Support\CompanyContextStore;
use App\Support\CompanyMutationRecorder;
use Illuminate\Database\Eloquent\Model;

final class SuperAdminActionRecorder implements CompanyMutationRecorder
{
    public const string ACTION_SELECTED_COMPANY = 'tenancy.selected_company';

    public const string ACTION_UPDATE_PROFILE = 'companies.update_profile';

    public const string ACTION_INVITE_COMPANY_USER = 'identity.invite_company_user';

    public const string ACTION_CHANGED_COMPANY_DATA = 'tenancy.changed_company_data';

    public function __construct(private readonly CompanyContextStore $store) {}

    public function record(Model $model): void
    {
        if ($this->store->isWithoutIsolation()) {
            return;
        }

        if ($this->store->actorRole() !== Role::SuperAdmin->value) {
            return;
        }

        $actorId = $this->store->actorId();

        if ($actorId === null) {
            return;
        }

        if (! $this->store->isCompanyBound()) {
            return;
        }

        if ($this->store->hasRecordedSuperAdminChange()) {
            return;
        }

        SuperAdminActionRecord::query()->create([
            'actor_id' => $actorId,
            'company_id' => $this->store->requireCompanyId(),
            'type' => SuperAdminActionType::ChangedCompanyData,
            'action' => $this->actionFor($model),
            'occurred_at' => now(),
        ]);

        $this->store->markSuperAdminChangeRecorded();
    }

    private function actionFor(Model $model): string
    {
        return match ($model->getTable()) {
            'companies', 'company_time_zone_versions', 'company_working_day_setting_versions' => self::ACTION_UPDATE_PROFILE,
            'invitations' => self::ACTION_INVITE_COMPANY_USER,
            default => self::ACTION_CHANGED_COMPANY_DATA,
        };
    }
}
