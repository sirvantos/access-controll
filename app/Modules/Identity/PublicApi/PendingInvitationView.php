<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

use Carbon\CarbonInterface;

final readonly class PendingInvitationView
{
    public function __construct(
        public int $id,
        public string $email,
        public Role $role,
        public CarbonInterface $expiresAt,
    ) {}
}
