<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

enum InvitationState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
