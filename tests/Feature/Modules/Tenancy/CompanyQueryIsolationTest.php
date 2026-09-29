<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

it('scopes employee search and counts to the bound company', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    globexEmployeeNumberSeventeen(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);

    $context->run($acme->id, function () use ($acme): void {
        $matches = Employee::query()
            ->where('name', 'like', '%Sarsenova%')
            ->get();

        expect($matches)->toHaveCount(0)
            ->and(Employee::query()->count())->toBe(1)
            ->and(Employee::query()->where('employee_number', '17')->count())->toBe(1);

        $paged = Employee::query()->orderBy('name')->paginate(1, ['*'], 'page', 1);

        expect($paged->total())->toBe(1)
            ->and($paged->items()[0]->company_id)->toBe($acme->id);
    });
});

it('scopes terminal and access event queries to the bound company', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-20 12:00:00'));

    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $globexTerminal = globexTerminal(['company_id' => $globex->id]);
    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);

    $context = app(CompanyContext::class);

    $context->run($acme->id, function () use ($acme, $acmeTerminal, $acmeEmployee): void {
        AccessEvent::factory()->create([
            'company_id' => $acme->id,
            'terminal_id' => $acmeTerminal->id,
            'employee_number' => '17',
            'employee_id' => $acmeEmployee->id,
            'created_at' => Carbon::parse('2026-06-20 08:00:00'),
        ]);
    });

    $context->run($globex->id, function () use ($globex, $globexTerminal): void {
        AccessEvent::factory()->create([
            'company_id' => $globex->id,
            'terminal_id' => $globexTerminal->id,
            'employee_number' => '17',
            'employee_id' => null,
            'created_at' => Carbon::parse('2026-06-20 08:00:00'),
        ]);
    });

    $context->run($acme->id, function (): void {
        expect(Terminal::query()->where('name', TENANCY_SHARED_TERMINAL_NAME)->count())->toBe(1)
            ->and(AccessEvent::query()->whereDate('created_at', '2026-06-20')->count())->toBe(1)
            ->and(AccessEvent::query()->orderBy('id')->paginate(10)->total())->toBe(1);
    });
});

it('scopes generated export files to the bound company', function (): void {
    Storage::fake('local');

    $acme = acmeCompany();
    $globex = globexCompany();
    acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    globexEmployeeNumberSeventeen(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);

    $buildExportPayload = fn (): string => json_encode([
        'employees' => Employee::query()
            ->orderBy('id')
            ->get(['employee_number', 'name'])
            ->map(fn (Employee $employee): array => [
                'employee_number' => $employee->employee_number,
                'name' => $employee->name,
            ])
            ->values()
            ->all(),
    ], JSON_THROW_ON_ERROR);

    $acmeExport = $context->run($acme->id, function () use ($acme, $buildExportPayload): CompanyMedia {
        $media = CompanyMedia::factory()->create([
            'company_id' => $acme->id,
            'kind' => CompanyMediaKind::Export,
            'content_type' => 'application/json',
        ]);

        Storage::disk('local')->put($media->path, $buildExportPayload());

        return $media;
    });

    $globexExport = $context->run($globex->id, function () use ($globex, $buildExportPayload): CompanyMedia {
        $media = CompanyMedia::factory()->create([
            'company_id' => $globex->id,
            'kind' => CompanyMediaKind::Export,
            'content_type' => 'application/json',
        ]);

        Storage::disk('local')->put($media->path, $buildExportPayload());

        return $media;
    });

    $context->run($acme->id, function () use ($acmeExport, $globexExport): void {
        expect(CompanyMedia::query()->where('kind', CompanyMediaKind::Export)->count())->toBe(1)
            ->and(CompanyMedia::query()->whereKey($globexExport->id)->exists())->toBeFalse();

        $encoded = Storage::disk('local')->get($acmeExport->path);
        $payload = json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);

        expect($payload['employees'])->toHaveCount(1)
            ->and($payload['employees'][0]['employee_number'])->toBe('17')
            ->and($payload['employees'][0]['name'])->toBe('Acme Seventeen')
            ->and($encoded)->not->toContain('Aigerim Sarsenova')
            ->and($encoded)->not->toContain('Globex');
    });
});
