<?php

declare(strict_types=1);

namespace Tests\Support\Identity;

use Illuminate\Log\Events\MessageLogged;

final class MessageLoggedBucket
{
    /**
     * @var list<MessageLogged>
     */
    public array $events = [];
}
