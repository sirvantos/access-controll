<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\CompanyMedia;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\Models\Terminal;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('treats Globex tenancy records as missing under Acme context', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $globexEmployee = globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $globexTerminal = globexTerminal(['company_id' => $globex->id]);
    $globexMedia = tenancyCompanyMedia(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);
    $missingId = 9_999_993;

    $context->run($acme->id, function () use ($globexEmployee, $globexTerminal, $globexMedia, $missingId): void {
        expect(fn () => Employee::query()->findOrFail($globexEmployee->id))->toThrow(ModelNotFoundException::class);
        expect(fn () => Employee::query()->findOrFail($missingId))->toThrow(ModelNotFoundException::class);
        expect(fn () => Terminal::query()->findOrFail($globexTerminal->id))->toThrow(ModelNotFoundException::class);
        expect(fn () => Terminal::query()->findOrFail($missingId))->toThrow(ModelNotFoundException::class);
        expect(fn () => CompanyMedia::query()->findOrFail($globexMedia->id))->toThrow(ModelNotFoundException::class);
        expect(fn () => CompanyMedia::query()->findOrFail($missingId))->toThrow(ModelNotFoundException::class);
    });
});

it('refuses to attach Globex media or terminals to Acme rows', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $globexMedia = tenancyCompanyMedia(['company_id' => $globex->id]);
    $globexTerminal = globexTerminal(['company_id' => $globex->id]);

    $context = app(CompanyContext::class);

    $context->run($acme->id, function () use ($acmeEmployee, $globexMedia): void {
        expect(fn () => $acmeEmployee->forceFill(['photo_media_id' => $globexMedia->id])->save())
            ->toThrow(ModelNotFoundException::class);
    });

    $globexMedia->refresh();
    $acmeEmployee->refresh();

    expect($acmeEmployee->photo_media_id)->not->toBe($globexMedia->id);

    $context->run($acme->id, function () use ($acme, $globexTerminal): void {
        expect(fn () => AccessEvent::query()->create([
            'company_id' => $acme->id,
            'terminal_id' => $globexTerminal->id,
            'employee_number' => '17',
            'employee_id' => null,
            'created_at' => now(),
        ]))->toThrow(ModelNotFoundException::class);
    });

    expect(AccessEvent::query()->withoutGlobalScopes()->where('terminal_id', $globexTerminal->id)->count())->toBe(0);
});
