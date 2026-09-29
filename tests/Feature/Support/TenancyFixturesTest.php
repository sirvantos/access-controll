<?php

declare(strict_types=1);

it('documents overlapping tenancy fixture values for isolation scans', function (): void {
    $acme = acmeCompany();
    $globex = globexCompany();

    $acmeEmployee = acmeEmployeeNumberSeventeen(['company_id' => $acme->id]);
    $globexEmployee = globexEmployeeNumberSeventeen(['company_id' => $globex->id]);
    $acmeTerminal = acmeTerminal(['company_id' => $acme->id]);
    $globexTerminal = globexTerminal(['company_id' => $globex->id]);

    expect($acmeEmployee->employee_number)->toBe('17')
        ->and($acmeEmployee->company_id)->toBe($acme->id)
        ->and($acmeEmployee->name)->not->toBe('Aigerim Sarsenova')
        ->and($globexEmployee->employee_number)->toBe('17')
        ->and($globexEmployee->company_id)->toBe($globex->id)
        ->and($globexEmployee->name)->toBe('Aigerim Sarsenova')
        ->and($acmeTerminal->name)->toBe($globexTerminal->name)
        ->and($acmeTerminal->company_id)->toBe($acme->id)
        ->and($globexTerminal->company_id)->toBe($globex->id);
});
