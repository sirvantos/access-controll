<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

use Illuminate\Contracts\Auth\Authenticatable;

interface Actor extends Authenticatable
{
    public function actorId(): int;

    public function actorRole(): Role;

    public function actorCompanyId(): ?int;

    public function sessionVersion(): int;
}
