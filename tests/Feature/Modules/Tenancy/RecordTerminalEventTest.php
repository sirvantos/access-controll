<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\RecordTerminalEventAction;
use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\PublicApi\CompanyContext;

it('records terminal events only for the terminal company employee match', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $globexEmployee = globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $action = app(RecordTerminalEventAction::class);

    $action($acmeTerminal->id, '17');

    $context = app(CompanyContext::class);

    $acmeEvents = $context->run($acme->id, fn () => AccessEvent::query()->get());
    $globexEvents = $context->run($globex->id, fn () => AccessEvent::query()->get());

    expect($acmeEvents)->toHaveCount(1)
        ->and($acmeEvents->first()->employee_id)->toBe($acmeEmployee->id)
        ->and($acmeEvents->first()->company_id)->toBe($acme->id)
        ->and($globexEvents)->toHaveCount(0);

    $context->run($globex->id, function () use ($globexEmployee): void {
        expect($globexEmployee->refresh()->accessEvents)->toHaveCount(0);
    });
});

it('leaves employee_id null when the number exists only in another company', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();
    globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $action = app(RecordTerminalEventAction::class);

    $action($acmeTerminal->id, '17');

    $context = app(CompanyContext::class);
    $event = $context->run($acme->id, fn () => AccessEvent::query()->sole());

    expect($event->employee_id)->toBeNull()
        ->and($event->employee_number)->toBe('17');
});

it('does not insert a row for an unknown terminal', function (): void {
    $action = app(RecordTerminalEventAction::class);

    $action(9_999_999, '17');

    $context = app(CompanyContext::class);

    expect($context->withoutIsolation(fn () => AccessEvent::query()->count()))->toBe(0);
});
