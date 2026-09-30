<?php

namespace App\Responses\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** PRD §7.11: once a follow-up's shared session has ended, nobody saves or submits it again. */
final class FollowUpCompleted extends Conflict
{
    public function __construct()
    {
        parent::__construct('FOLLOW_UP_COMPLETED', 'This follow-up has already been completed.');
    }
}
