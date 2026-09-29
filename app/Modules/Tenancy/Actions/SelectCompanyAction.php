<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Companies\PublicApi\CompanyDirectory;
use App\Modules\Identity\PublicApi\Actor;
use App\Modules\Tenancy\Data\SuperAdminActionType;
use App\Modules\Tenancy\Models\SuperAdminActionRecord;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\SelectedCompanyView;
use App\Modules\Tenancy\Services\SuperAdminActionRecorder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class SelectCompanyAction
{
    public function __construct(
        private CompanyDirectory $companies,
        private CompanyContext $companyContext,
    ) {}

    public function __invoke(Actor $actor, int $companyId): SelectedCompanyView
    {
        $name = $this->companyContext->withoutIsolation(
            fn (): ?string => $this->companies->name($companyId),
        );

        throw_unless(is_string($name), ModelNotFoundException::class);

        SuperAdminActionRecord::query()->create([
            'actor_id' => $actor->actorId(),
            'company_id' => $companyId,
            'type' => SuperAdminActionType::SelectedCompany,
            'action' => SuperAdminActionRecorder::ACTION_SELECTED_COMPANY,
            'occurred_at' => now(),
        ]);

        return new SelectedCompanyView(id: $companyId, name: $name);
    }
}
