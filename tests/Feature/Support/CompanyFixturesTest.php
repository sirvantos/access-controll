<?php

declare(strict_types=1);

it('returns the documented company fixture values', function () {
    $phoneDigits = preg_replace('/\D/', '', sampleCompanyPhone());

    expect(sampleCompanyBin())->toBe('123456789012')
        ->and(sampleCompanyPhone())->toBe('+7 (700) 123-45-67')
        ->and(mb_strlen(sampleCompanyPhone()))->toBeLessThanOrEqual(32)
        ->and(strlen((string) $phoneDigits))->toBeGreaterThanOrEqual(10)
        ->and(strlen((string) $phoneDigits))->toBeLessThanOrEqual(15)
        ->and(defaultWorkingDaySettingsPayload())->toBe([
            'start_time' => '09:00',
            'end_time' => '18:00',
            'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'break_duration_minutes' => 60,
            'break_deducted' => true,
            'lateness_grace_minutes' => 0,
        ])
        ->and(companyCreateOptionalDetails())->toBe([
            'bin' => '123456789012',
            'contact_person' => 'Acme Contact',
            'phone' => '+7 (700) 123-45-67',
            'email' => 'office@acme.test',
        ]);
});

it('applies overrides without replacing the rest of a company fixture', function () {
    expect(defaultWorkingDaySettingsPayload([
        'break_duration_minutes' => 30,
    ]))->toMatchArray([
        'start_time' => '09:00',
        'break_duration_minutes' => 30,
        'lateness_grace_minutes' => 0,
    ])
        ->and(companyCreateOptionalDetails([
            'email' => 'desk@acme.test',
        ]))->toMatchArray([
            'bin' => '123456789012',
            'phone' => '+7 (700) 123-45-67',
            'email' => 'desk@acme.test',
        ]);
});
