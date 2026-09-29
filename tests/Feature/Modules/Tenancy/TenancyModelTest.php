<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Tenancy\Data\SuperAdminActionType;
use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\SuperAdminActionRecord;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use App\Modules\Tenancy\PublicApi\CompanyMediaKind;
use Illuminate\Support\Str;

it('casts tenancy model attributes and wires same-module relations', function (): void {
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $media = $context->run($acme->id, fn (): CompanyMedia => CompanyMedia::factory()->create([
        'company_id' => $acme->id,
        'kind' => CompanyMediaKind::EmployeePhoto,
    ]));

    $employee = $context->run($acme->id, fn (): Employee => Employee::factory()->create([
        'company_id' => $acme->id,
        'employee_number' => '42',
        'name' => 'Worker',
        'photo_media_id' => $media->id,
    ]));

    $terminal = $context->run($acme->id, fn (): Terminal => Terminal::factory()->create([
        'company_id' => $acme->id,
        'name' => 'Front door',
    ]));

    $event = $context->run($acme->id, fn (): AccessEvent => AccessEvent::factory()->create([
        'company_id' => $acme->id,
        'terminal_id' => $terminal->id,
        'employee_number' => '42',
        'employee_id' => $employee->id,
        'snapshot_media_id' => $media->id,
    ]));

    $context->run($acme->id, function () use ($media, $employee, $terminal, $event): void {
        expect($media->kind)->toBe(CompanyMediaKind::EmployeePhoto)
            ->and($employee->photoMedia?->is($media))->toBeTrue()
            ->and($terminal->accessEvents->contains($event))->toBeTrue()
            ->and($employee->accessEvents->contains($event))->toBeTrue()
            ->and($event->terminal->is($terminal))->toBeTrue()
            ->and($event->employee?->is($employee))->toBeTrue();
    });
});

it('creates tenancy rows inside withoutIsolation when context is unbound', function (): void {
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $employee = $context->withoutIsolation(fn (): Employee => Employee::factory()->create([
        'company_id' => $acme->id,
        'employee_number' => '99',
        'name' => 'Isolated seed',
    ]));

    expect($employee->company_id)->toBe($acme->id);
});

it('refuses to persist an employee without a company id when context is unbound', function (): void {
    expect(fn (): Employee => Employee::factory()->create([
        'company_id' => null,
        'employee_number' => '1',
        'name' => 'Missing company',
    ]))->toThrow(CompanyContextRequiredException::class);
});

it('stores super admin action records without using company context on create', function (): void {
    $acme = acmeCompany();
    $actor = ownerSuperAdmin();

    $record = SuperAdminActionRecord::query()->create([
        'actor_id' => $actor->id,
        'company_id' => $acme->id,
        'type' => SuperAdminActionType::SelectedCompany,
        'action' => 'tenancy.selected_company',
        'occurred_at' => now(),
    ]);

    expect($record->company_id)->toBe($acme->id)
        ->and($record->type)->toBe(SuperAdminActionType::SelectedCompany);
});

it('generates company media factories with uuid public ids', function (): void {
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $media = $context->run($acme->id, fn (): CompanyMedia => CompanyMedia::factory()->create([
        'company_id' => $acme->id,
    ]));

    expect(Str::isUuid($media->public_id))->toBeTrue()
        ->and($media->disk)->toBe('local');
});
