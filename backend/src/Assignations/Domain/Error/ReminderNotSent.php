<?php

namespace App\Assignations\Domain\Error;

use App\Shared\Domain\Error\UpstreamFailed;

/** 502: the email provider refused the reminder (PRD §7.13); the day is not marked. */
final class ReminderNotSent extends UpstreamFailed
{
    public function __construct()
    {
        parent::__construct('REMINDER_NOT_SENT', 'The reminder could not be sent.');
    }
}
