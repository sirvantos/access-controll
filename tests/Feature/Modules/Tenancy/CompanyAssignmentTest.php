<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Identity\Models\Invitation;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

it('assigns new invitations to the signed-in admin company', function (): void {
    Notification::fake();
    $acme = acmeCompany();
    $globex = globexCompany();
    $admin = acmeAdmin(['company_id' => $acme->id]);

    signedInAs($admin)
        ->postJson('/api/v1/company/invitations', [
            'email' => 'scoped@acme.test',
            'role' => 'viewer',
            'company_id' => $globex->id,
        ])
        ->assertCreated();

    $invitation = Invitation::query()->where('email', 'scoped@acme.test')->first();

    expect($invitation)->not->toBeNull()
        ->and($invitation->company_id)->toBe($acme->id)
        ->and(Invitation::query()->where('company_id', $globex->id)->count())->toBe(0);
});

it('forces tenancy models into the bound company even when attributes name another company', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $context = app(CompanyContext::class);

    $context->run($acme->id, function () use ($acme, $globex): void {
        $employee = Employee::query()->create([
            'company_id' => $globex->id,
            'employee_number' => '88',
            'name' => 'Scoped employee',
        ]);

        $terminal = Terminal::query()->create([
            'company_id' => $globex->id,
            'name' => 'Scoped terminal',
        ]);

        $media = CompanyMedia::query()->create([
            'company_id' => $globex->id,
            'public_id' => (string) Str::uuid(),
            'kind' => CompanyMediaKind::EmployeePhoto,
            'disk' => 'local',
            'path' => $acme->id.'/assignment-test',
            'content_type' => 'image/jpeg',
        ]);

        expect($employee->company_id)->toBe($acme->id)
            ->and($terminal->company_id)->toBe($acme->id)
            ->and($media->company_id)->toBe($acme->id);
    });
});

it('refuses to move an existing record into another company on update', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $employee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $context = app(CompanyContext::class);

    $context->run($acme->id, function () use ($employee, $globex): void {
        $employee->company_id = $globex->id;
        $employee->name = 'Renamed in Acme';
        $employee->save();
    });

    $employee->refresh();

    expect($employee->company_id)->toBe($acme->id)
        ->and($employee->name)->toBe('Renamed in Acme');
});

it('refuses to persist company-owned rows without a bound company context', function (): void {
    expect(fn (): Employee => Employee::factory()->create([
        'company_id' => null,
        'employee_number' => '1',
        'name' => 'No company',
    ]))->toThrow(CompanyContextRequiredException::class);

    expect(fn (): Terminal => Terminal::factory()->create([
        'company_id' => null,
        'name' => 'No company terminal',
    ]))->toThrow(CompanyContextRequiredException::class);

    expect(fn (): CompanyMedia => CompanyMedia::factory()->create([
        'company_id' => null,
    ]))->toThrow(CompanyContextRequiredException::class);
});
