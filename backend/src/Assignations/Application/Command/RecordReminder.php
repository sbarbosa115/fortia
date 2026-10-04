<?php

namespace App\Assignations\Application\Command;

/**
 * The daily run already reminded the $reminded respondents of a follow-up (each in their own email or in a digest
 * with their other pending follow-ups, PRD §7.13): the account's root users get the status and the day is marked.
 * A failed status email is logged and the day is not marked. Returns a ReminderOutcome.
 */
final class RecordReminder
{
    public function __construct(
        public readonly string $assignationsId,
        public readonly int $reminded,
    ) {
    }
}
