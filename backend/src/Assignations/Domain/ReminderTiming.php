<?php

namespace App\Assignations\Domain;

/**
 * When a follow-up's reminder goes out and what its subject says (PRD §7.13): every day at 13:00 UTC for active
 * follow-ups that are not complete and were not reminded on the same UTC day; the subject depends on the whole days
 * left until the due date, counted in UTC: no date / due today / due in N days / N days overdue.
 *
 *     ReminderTiming::of('2026-10-03', '2026-09-30'); // ['kind' => 'due_in', 'days' => 3]
 */
final class ReminderTiming
{
    public const NO_DATE = 'no_date';
    public const DUE_TODAY = 'due_today';
    public const DUE_IN = 'due_in';
    public const OVERDUE = 'overdue';

    /**
     * @param string|null $dueDate YYYY-MM-DD
     * @param string      $today   the UTC calendar date
     *
     * @return array{kind: string, days: int} days: left (due_in) or late (overdue), 0 otherwise
     */
    public static function of(?string $dueDate, string $today): array
    {
        if (null === $dueDate || '' === $dueDate) {
            return ['kind' => self::NO_DATE, 'days' => 0];
        }
        $days = (int) (new \DateTimeImmutable($today.'T00:00:00Z'))->diff(new \DateTimeImmutable($dueDate.'T00:00:00Z'))->format('%r%a');

        return match (true) {
            0 === $days => ['kind' => self::DUE_TODAY, 'days' => 0],
            $days > 0 => ['kind' => self::DUE_IN, 'days' => $days],
            default => ['kind' => self::OVERDUE, 'days' => -$days],
        };
    }

    /** Whether a reminder already went out on the UTC day of $now. */
    public static function remindedToday(?\DateTimeImmutable $lastReminderSentAt, \DateTimeImmutable $now): bool
    {
        if (null === $lastReminderSentAt) {
            return false;
        }
        $utc = new \DateTimeZone('UTC');

        return $lastReminderSentAt->setTimezone($utc)->format('Y-m-d') === $now->setTimezone($utc)->format('Y-m-d');
    }
}
