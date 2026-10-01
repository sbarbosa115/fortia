<?php

namespace App\Assignations\Application\Command;

/** What a SendReminder did: sent to $recipients respondents, or skipped with a reason (the daily run only). */
final class ReminderOutcome
{
    private function __construct(
        public readonly bool $sent,
        public readonly int $recipients,
        public readonly ?string $skipped,
    ) {
    }

    public static function sent(int $recipients): self
    {
        return new self(true, $recipients, null);
    }

    public static function skipped(string $reason): self
    {
        return new self(false, 0, $reason);
    }
}
