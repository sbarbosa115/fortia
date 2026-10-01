<?php

namespace App\Assignations\UI\Http\Output;

/** POST /assignations/{id}/reminders (PRD §7.13): how many respondents the reminder went to. */
final class ReminderSentOutput
{
    public function __construct(public readonly int $recipients)
    {
    }
}
