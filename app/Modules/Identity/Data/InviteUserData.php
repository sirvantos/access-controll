<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\PublicApi\Role;
use Spatie\LaravelData\Data;

final class InviteUserData extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly Role $role,
    ) {}
}
