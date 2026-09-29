<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Modules\Tenancy\Jobs\ProveCompanyScopedWorkJob;
use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\Models\Employee;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Support\Facades\Queue;

it('scopes queued company work to the company passed into run', function (): void {
    Queue::fake();

    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $globexEmployee = globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $globexTerminal = globexTerminal(['company_id' => $globex->id]);
    $context = app(CompanyContext::class);

    $acmeEvent = $context->run($acme->id, fn (): AccessEvent => AccessEvent::factory()->create([
        'company_id' => $acme->id,
        'terminal_id' => $acmeTerminal->id,
        'employee_number' => ProveCompanyScopedWorkJob::TARGET_EMPLOYEE_NUMBER,
        'employee_id' => $acmeEmployee->id,
    ]));
    $globexEvent = $context->run($globex->id, fn (): AccessEvent => AccessEvent::factory()->create([
        'company_id' => $globex->id,
        'terminal_id' => $globexTerminal->id,
        'employee_number' => ProveCompanyScopedWorkJob::TARGET_EMPLOYEE_NUMBER,
        'employee_id' => $globexEmployee->id,
    ]));

    ProveCompanyScopedWorkJob::dispatch($acme->id, true);

    Queue::assertPushed(ProveCompanyScopedWorkJob::class);

    (new ProveCompanyScopedWorkJob($acme->id, true))->handle($context);

    $acmeEmployee->refresh();
    $globexEmployee->refresh();

    expect($acmeEmployee->name)->toBe(ProveCompanyScopedWorkJob::SCOPED_EMPLOYEE_NAME)
        ->and($globexEmployee->name)->toBe('Aigerim Sarsenova')
        ->and($context->withoutIsolation(fn (): bool => AccessEvent::query()->whereKey($acmeEvent->id)->doesntExist()))->toBeTrue()
        ->and($context->withoutIsolation(fn (): bool => AccessEvent::query()->whereKey($globexEvent->id)->exists()))->toBeTrue();
});

it('refuses company records when a job omits run', function (): void {
    Queue::fake();

    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $globexEmployee = globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $context = app(CompanyContext::class);

    ProveCompanyScopedWorkJob::dispatch($acme->id, false);

    Queue::assertPushed(ProveCompanyScopedWorkJob::class);

    expect(fn () => (new ProveCompanyScopedWorkJob($acme->id, false))->handle($context))
        ->toThrow(CompanyContextRequiredException::class);

    expect($acmeEmployee->refresh()->name)->toBe('Acme Seventeen')
        ->and($globexEmployee->refresh()->name)->toBe('Aigerim Sarsenova')
        ->and($context->withoutIsolation(fn (): int => Employee::query()->count()))->toBe(2);
});
