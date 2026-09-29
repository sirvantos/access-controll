<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;

final class RecordTerminalEventAction
{
    public function __construct(private readonly CompanyContext $companyContext) {}

    public function __invoke(int $terminalId, string $employeeNumber, ?int $snapshotMediaId = null): void
    {
        $terminal = $this->companyContext->withoutIsolation(
            fn (): ?Terminal => Terminal::query()->find($terminalId),
        );

        if ($terminal === null) {
            return;
        }

        $this->companyContext->run($terminal->company_id, function () use ($terminal, $employeeNumber, $snapshotMediaId): void {
            $employee = Employee::query()
                ->where('employee_number', $employeeNumber)
                ->first();

            AccessEvent::query()->create([
                'company_id' => $terminal->company_id,
                'terminal_id' => $terminal->id,
                'employee_number' => $employeeNumber,
                'employee_id' => $employee?->id,
                'snapshot_media_id' => $snapshotMediaId,
                'created_at' => now(),
            ]);
        });
    }
}
