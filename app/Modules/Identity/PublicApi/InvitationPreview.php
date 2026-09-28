<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

use Carbon\CarbonInterface;

final readonly class InvitationPreview
{
    public function __construct(
        public string $email,
        public Role $role,
        public CarbonInterface $expiresAt,
    ) {}
}
