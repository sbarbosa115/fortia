<?php

namespace App\Chat\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409 NOT_CONFIRMED: only the user's own explicit yes confirms (PRD §7.19). */
final class NotConfirmed extends Conflict
{
    public function __construct(string $what)
    {
        parent::__construct('NOT_CONFIRMED', "The user has not explicitly confirmed $what in their last message. Ask them and wait for their yes.");
    }
}
