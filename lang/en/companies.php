<?php

declare(strict_types=1);

return [
    'bin' => 'The BIN must be exactly :length digits.',
    'phone' => 'The phone must contain 10 to 15 digits, may start with +, and may include spaces, hyphens, and parentheses.',
    'end_time_after_start' => 'The end time must be later than the start time.',
    'working_days_required' => 'Select at least one working day.',
    'break_duration_range' => 'The break duration must be less than the working day length.',
    'lateness_grace_range' => 'The lateness grace period must be less than the working day length.',
];
