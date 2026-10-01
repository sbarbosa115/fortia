<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** 400: reminders, reviews and retries only exist for follow-ups (PRD §7.11, §7.13). */
final class NotAFollowUp extends Rejected
{
    public function __construct()
    {
        parent::__construct('NOT_A_FOLLOW_UP', 'This only applies to follow-up assignations.');
    }
}
