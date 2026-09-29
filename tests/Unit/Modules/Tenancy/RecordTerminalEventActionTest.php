<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\RecordTerminalEventAction;
use App\Modules\Tenancy\Models\AccessEvent;
use App\Modules\Tenancy\PublicApi\CompanyContext;

it('returns without inserting when the terminal id is unknown', function (): void {
    app(RecordTerminalEventAction::class)(0, '1');

    $context = app(CompanyContext::class);

    expect($context->withoutIsolation(fn () => AccessEvent::query()->count()))->toBe(0);
});
