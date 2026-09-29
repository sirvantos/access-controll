<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Data;

enum SuperAdminActionType: string
{
    case SelectedCompany = 'selected_company';
    case ChangedCompanyData = 'changed_company_data';
}
