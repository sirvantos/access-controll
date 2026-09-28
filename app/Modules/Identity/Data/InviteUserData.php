<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\PublicApi\Role;
use Illuminate\Support\Stringable;
use Spatie\LaravelData\Data;

final class InviteUserData extends Data
{
    public function __construct(
        public readonly Stringable $email,
        public readonly Role $role,
    ) {}
}
