<?php

declare(strict_types=1);

use App\Exceptions\CompanyContextRequiredException;
use App\Exceptions\CompanyNotSelectedException;
use App\Exceptions\EmployeeNumberTakenException;
use Illuminate\Http\Request;

it('renders company not selected with localized message and error code', function () {
    $request = Request::create('/api/v1/company/users', 'GET');

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);

        $response = (new CompanyNotSelectedException)->render($request);

        expect($response->getStatusCode())->toBe(409)
            ->and($response->getData(true))->toBe([
                'message' => __('tenancy.company_not_selected'),
                'error_code' => CompanyNotSelectedException::ERROR_CODE,
            ]);
    }
});

it('renders employee number taken with localized field error', function () {
    $request = Request::create('/api/v1/company/employees', 'POST');

    foreach (['en', 'ru'] as $locale) {
        app()->setLocale($locale);

        $message = __('tenancy.employee_number_taken');
        $response = (new EmployeeNumberTakenException)->render($request);

        expect($response->getStatusCode())->toBe(422)
            ->and($response->getData(true))->toBe([
                'message' => $message,
                'errors' => [
                    'employee_number' => [$message],
                ],
            ]);
    }
});

it('renders company context required as a server error', function () {
    $request = Request::create('/api/v1/company/users', 'GET');
    $response = (new CompanyContextRequiredException)->render($request);

    expect($response->getStatusCode())->toBe(500)
        ->and($response->getData(true))->toBe(['message' => 'Server Error']);
});

it('does not report company not selected to the log', function () {
    $logs = captureLogEvents();

    report(new CompanyNotSelectedException);

    expectNothingLogged($logs);
});

it('does not report employee number taken to the log', function () {
    $logs = captureLogEvents();

    report(new EmployeeNumberTakenException);

    expectNothingLogged($logs);
});
