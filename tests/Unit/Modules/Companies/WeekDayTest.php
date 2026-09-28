<?php

declare(strict_types=1);

use App\Modules\Companies\PublicApi\WeekDay;

it('lists the seven weekday identifiers from the company constraints', function () {
    expect(array_map(fn (WeekDay $day): string => $day->value, WeekDay::cases()))->toBe([
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
    ]);
});
