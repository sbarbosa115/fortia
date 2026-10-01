<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** 409: answers are reviewed (and sent for correction) only once the follow-up is complete (PRD §7.11). */
final class FollowUpNotCompleted extends Conflict
{
    public function __construct()
    {
        parent::__construct('FOLLOW_UP_NOT_COMPLETED', 'The follow-up must be complete before it is reviewed.');
    }
}
