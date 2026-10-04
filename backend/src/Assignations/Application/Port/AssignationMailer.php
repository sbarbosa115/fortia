<?php

namespace App\Assignations\Application\Port;

use App\Shared\Application\Mail\MailNotSent;

/**
 * The emails of assignations (PRD §7.13, §7.11 retry, §7.21), in the account's language (`es` | `en`). Each method
 * sends one message to each address in $to (respondents never see each other) and throws when one is not sent.
 *
 * $assignation: {assignations_id, customer_id, name, organization_name, due_date}; they go through the
 * account's own SMTP server when it has one; $timing: ReminderTiming::of().
 */
interface AssignationMailer
{
    /**
     * The reminder to the respondents, with the link to /a/{id}.
     *
     * @param array<string, mixed>           $assignation
     * @param list<string>                   $to
     * @param array{kind: string, days: int} $timing
     *
     * @throws MailNotSent
     */
    public function sendReminder(array $assignation, array $to, string $locale, array $timing): void;

    /**
     * The status email to the account's root users: progress, percentage, due date, how many were reminded, and the
     * link to the console's /assignations/{id}.
     *
     * @param array<string, mixed>                            $assignation
     * @param list<string>                                    $to
     * @param array{kind: string, days: int}                  $timing
     * @param array{completed: int, total: int, percent: int} $progress
     *
     * @throws MailNotSent
     */
    public function sendStatus(array $assignation, array $to, string $locale, array $timing, array $progress, int $reminded): void;

    /**
     * "Send for correction": attempt $attempt is open, with the link to /a/{id}.
     *
     * @param array<string, mixed> $assignation
     * @param list<string>         $to
     *
     * @throws MailNotSent
     */
    public function sendRetry(array $assignation, array $to, string $locale, int $attempt, int $rejected): void;
}
