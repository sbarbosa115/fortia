<?php

namespace App\Chat\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409 WRITE_QUEUE_FULL: PRD §7.19 — at most 50 writes wait for the user's yes. */
final class WriteQueueFull extends Conflict
{
    public function __construct(int $max)
    {
        parent::__construct('WRITE_QUEUE_FULL', "At most $max changes can wait for the user's confirmation. Ask the user to confirm or discard them first.");
    }
}
