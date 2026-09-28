<?php

declare(strict_types=1);

use Access\Factory\Contract;

it('gives an agent eighty minutes', function () {
    expect(Contract::load(dirname(__DIR__, 3))->agentTimeout())->toBe(4800)
        ->and((new Contract([]))->agentTimeout())->toBe(4800);
});
