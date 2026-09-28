<?php

declare(strict_types=1);

namespace App\Modules\Companies\Data;

final class WorkingDaySettingDefaults
{
    public const string DEFAULT_TIME_ZONE = 'Asia/Almaty';

    public const string DEFAULT_START_TIME = '09:00';

    public const string DEFAULT_END_TIME = '18:00';

    /** @var list<string> */
    public const array DEFAULT_WORKING_DAYS = [
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
    ];

    public const int DEFAULT_BREAK_DURATION_MINUTES = 60;

    public const bool DEFAULT_BREAK_DEDUCTED = true;

    public const int DEFAULT_LATENESS_GRACE_MINUTES = 0;

    private function __construct() {}
}
