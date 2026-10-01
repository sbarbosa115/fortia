<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: a complete follow-up takes no more logins nor reminders (PRD §7.11, §7.13). */
final class FollowUpCompleted extends Conflict
{
    public function __construct()
    {
        parent::__construct('FOLLOW_UP_COMPLETED', 'This follow-up has already been completed.');
    }
}
