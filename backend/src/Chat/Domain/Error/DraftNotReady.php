<?php

namespace App\Chat\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409 DRAFT_NOT_READY: the draft cannot move on yet (basics missing or unconfirmed, no questions, no tiers…). */
final class DraftNotReady extends Conflict
{
    public function __construct(string $message)
    {
        parent::__construct('DRAFT_NOT_READY', $message);
    }
}
