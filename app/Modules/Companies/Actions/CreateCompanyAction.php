<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Data\CreateCompanyData;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\PublicApi\CompanySummary;
use App\Modules\Identity\PublicApi\FirstAdminInvitations;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CreateCompanyAction
{
    public function __construct(private FirstAdminInvitations $firstAdminInvitations) {}

    public function __invoke(CreateCompanyData $data): CompanySummary
    {
        $company = DB::transaction(function () use ($data): Company {
            $company = Company::query()->create([
                'name' => $data->name,
            ]);

            $this->firstAdminInvitations->invite($company->id, $data->firstAdminEmail);

            return $company;
        });

        $createdAt = $company->created_at;

        throw_unless($createdAt instanceof CarbonInterface, LogicException::class);

        return new CompanySummary(
            id: $company->id,
            name: $company->name,
            isActive: $company->isActive(),
            awaitingFirstAdmin: $this->firstAdminInvitations->isAwaitingFirstAdmin($company->id),
            createdAt: $createdAt,
        );
    }
}
