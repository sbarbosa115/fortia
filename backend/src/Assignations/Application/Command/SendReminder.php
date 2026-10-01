<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * The reminder of a follow-up (PRD §7.13): the respondents (the audience's emails, deduplicated) receive the link to
 * /a/{id}; the account's root users receive the status (progress, percentage, due date, how many were reminded, the
 * console link) unless they are respondents themselves. The day is marked only when both emails went out.
 *
 * With a $caller it is the manual "Send reminder" (POST /assignations/{id}/reminders): another account's is 404 and
 * every refusal is an error (400 NOT_A_FOLLOW_UP, 409 FOLLOW_UP_COMPLETED, 422 NO_RECIPIENTS, 502
 * REMINDER_NOT_SENT). Without one it is the daily run, which skips (and logs) instead. Returns a ReminderOutcome.
 */
final class SendReminder
{
    public function __construct(
        public readonly string $assignationsId,
        public readonly ?Caller $caller = null,
    ) {
    }

    public function isManual(): bool
    {
        return null !== $this->caller;
    }
}
