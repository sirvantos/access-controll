<?php

declare(strict_types=1);

use App\Exceptions\EmployeeNumberTakenException;
use App\Modules\Tenancy\Actions\CreateEmployeeAction;
use App\Modules\Tenancy\PublicApi\CompanyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

it('refuses duplicate employee numbers in the same company with localized messages', function (): void {
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $context->run($acme->id, fn () => (new CreateEmployeeAction($context))('First Seventeen', '17'));

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);
        $logs = captureLogEvents();

        try {
            $context->run($acme->id, fn () => (new CreateEmployeeAction($context))('Second Seventeen', '17'));
            expect(false)->toBeTrue('Expected EmployeeNumberTakenException');
        } catch (EmployeeNumberTakenException $exception) {
            $response = $exception->render(Request::create('/api/v1/company/employees', 'POST'));
            $payload = $response->getData(true);
            $message = __('tenancy.employee_number_taken');

            expect($response->getStatusCode())->toBe(Response::HTTP_UNPROCESSABLE_ENTITY)
                ->and($payload['message'])->toBe($message)
                ->and($payload['errors']['employee_number'][0])->toBe($message);

            expectNothingLogged($logs);
        }
    }
});

it('creates an employee when the same number exists only in another company', function (): void {
    $acme = acmeCompany();
    globexEmployeeNumberSeventeen(['company_id' => globexCompany()->id]);
    $context = app(CompanyContext::class);

    $employee = $context->run($acme->id, fn () => (new CreateEmployeeAction($context))('Acme Seventeen', '17'));

    expect($employee->company_id)->toBe($acme->id)
        ->and($employee->employee_number)->toBe('17');
});

it('does not mention another company in the duplicate number response', function (): void {
    $acme = acmeCompany();
    $context = app(CompanyContext::class);

    $context->run($acme->id, fn () => (new CreateEmployeeAction($context))('First', '17'));

    try {
        $context->run($acme->id, fn () => (new CreateEmployeeAction($context))('Second', '17'));
    } catch (EmployeeNumberTakenException $exception) {
        $request = Request::create('/api/v1/company/employees', 'POST');
        $payload = json_encode($exception->render($request)->getData(true), JSON_THROW_ON_ERROR);

        expect($payload)->not->toContain('Globex');

        return;
    }

    expect(false)->toBeTrue('Expected EmployeeNumberTakenException');
});
