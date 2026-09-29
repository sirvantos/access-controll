<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Jobs;

use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ProveCompanyScopedWorkJob implements ShouldQueue
{
    use Queueable;

    public const string SCOPED_EMPLOYEE_NAME = 'Acme scoped job employee';

    public const string TARGET_EMPLOYEE_NUMBER = '17';

    public function __construct(
        public int $companyId,
        public bool $bindCompanyContext,
    ) {}

    public function handle(CompanyContext $companyContext): void
    {
        $mutate = function (): void {
            Employee::query()->where('employee_number', self::TARGET_EMPLOYEE_NUMBER)->update([
                'name' => self::SCOPED_EMPLOYEE_NAME,
            ]);
            AccessEvent::query()->delete();
        };

        if ($this->bindCompanyContext) {
            $companyContext->run($this->companyId, $mutate);

            return;
        }

        $mutate();
    }
}
