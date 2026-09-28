<?php

declare(strict_types=1);

namespace App\Modules\Companies\Actions;

use DateTimeZone;

final class ListTimeZonesAction
{
    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return DateTimeZone::listIdentifiers();
    }
}
