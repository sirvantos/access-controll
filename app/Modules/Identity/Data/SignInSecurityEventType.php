<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

enum SignInSecurityEventType: string
{
    case FailedAttempt = 'failed_attempt';
    case AttemptWhileBlocked = 'attempt_while_blocked';
    case AccountBlocked = 'account_blocked';
    case SourceBlocked = 'source_blocked';
}
