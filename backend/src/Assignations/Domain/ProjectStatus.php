<?php

namespace App\Assignations\Domain;

/**
 * The state rules of a project (PRD §7.12).
 *
 * State of an assignation within the project, first matching rule:
 *   1. complete → `completed` when its assignation does not require review, else `review` if in_review, `correction`
 *      if changes_requested, otherwise `approved`;
 *   2. overdue → `overdue` ("today" in UTC−12, so the due date itself is never overdue in any time zone);
 *   3. changes requested or attempt > 1 → `correction`;
 *   4. some progress → `progress`;
 *   5. otherwise → `pending`.
 *
 * Project state: the first found among its assignations in the order review, overdue, correction, progress, pending,
 * completed, approved; `empty` without assignations. progress_percent: the rounded average of each assignation's percent.
 */
final class ProjectStatus
{
    public const REVIEW = 'review';
    public const OVERDUE = 'overdue';
    public const CORRECTION = 'correction';
    public const PROGRESS = 'progress';
    public const PENDING = 'pending';
    public const COMPLETED = 'completed';
    public const APPROVED = 'approved';
    public const EMPTY = 'empty';

    /** The project state's priority (§7.12). */
    public const PRIORITY = [self::REVIEW, self::OVERDUE, self::CORRECTION, self::PROGRESS, self::PENDING, self::COMPLETED, self::APPROVED];

    /** The `status` filter of GET /projects (§8.9); `progress` includes `pending`, `completed` includes `approved`. */
    public const FILTERS = [self::REVIEW, self::PROGRESS, self::CORRECTION, self::OVERDUE, self::COMPLETED, self::APPROVED];

    /** "Today" for the overdue rule: the calendar date in UTC−12, the last time zone on Earth to reach a date. */
    public static function todayForOverdue(\DateTimeImmutable $now): string
    {
        return $now->setTimezone(new \DateTimeZone('-12:00'))->format('Y-m-d');
    }

    /** A YYYY-MM-DD due date is overdue once "today" (UTC−12) is after it. */
    public static function isOverdue(?string $dueDate, string $today): bool
    {
        return null !== $dueDate && '' !== $dueDate && $dueDate < $today;
    }

    /**
     * @param int         $attempts how many attempts the follow-up has (0 before anybody opens it)
     * @param string|null $dueDate  the date the assignation is due (its own, or the project's)
     * @param string      $today    todayForOverdue()
     */
    public static function ofAssignation(FollowUpProgress $progress, int $attempts, ?string $dueDate, string $today): string
    {
        if ($progress->ended) {
            return match ($progress->reviewStatus) {
                FollowUpProgress::IN_REVIEW => self::REVIEW,
                FollowUpProgress::CHANGES_REQUESTED => self::CORRECTION,
                FollowUpProgress::COMPLETED => self::COMPLETED,
                default => self::APPROVED,
            };
        }
        if (self::isOverdue($dueDate, $today)) {
            return self::OVERDUE;
        }
        if (FollowUpProgress::CHANGES_REQUESTED === $progress->reviewStatus || $attempts > 1) {
            return self::CORRECTION;
        }

        return $progress->hasProgress() ? self::PROGRESS : self::PENDING;
    }

    /** @param list<string> $assignationStates */
    public static function ofProject(array $assignationStates): string
    {
        foreach (self::PRIORITY as $state) {
            if (\in_array($state, $assignationStates, true)) {
                return $state;
            }
        }

        return self::EMPTY;
    }

    /** @param list<FollowUpProgress> $progress */
    public static function progressPercent(array $progress): int
    {
        if ([] === $progress) {
            return 0;
        }
        $sum = array_sum(array_map(static fn (FollowUpProgress $p): float => $p->percent(), $progress));

        return (int) round($sum / \count($progress));
    }

    /** Whether a project in $state is listed under the `status` filter $filter (§8.9). */
    public static function matchesFilter(string $state, string $filter): bool
    {
        return $state === $filter
            || (self::PROGRESS === $filter && self::PENDING === $state)
            || (self::COMPLETED === $filter && self::APPROVED === $state);
    }
}
