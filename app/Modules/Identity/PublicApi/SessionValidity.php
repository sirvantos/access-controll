<?php

declare(strict_types=1);

namespace App\Modules\Identity\PublicApi;

interface SessionValidity
{
    public function isCurrent(Actor $actor, ?int $storedSessionVersion): bool;
}
