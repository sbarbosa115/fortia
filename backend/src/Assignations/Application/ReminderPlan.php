<?php

namespace App\Assignations\Application;

use App\Assignations\Domain\Model\Assignation;

/**
 * A follow-up that can be reminded now (PRD §7.13): who gets the reminder, which root users get the status, and what
 * the emails show.
 */
final class ReminderPlan
{
    /**
     * @param array{assignations_id: string, customer_id: string, name: string, organization_name: string, project_name: string|null, due_date: string|null} $view
     * @param array{kind: string, days: int}                                                                                                                 $timing
     * @param list<string>                                                                                                                                   $recipients the respondents' lowercase emails
     * @param list<string>                                                                                                                                   $roots      the root users who are not respondents
     * @param array{completed: int, total: int, percent: int}                                                                                                $progress
     */
    public function __construct(
        public readonly Assignation $assignation,
        public readonly array $view,
        public readonly string $locale,
        public readonly array $timing,
        public readonly array $recipients,
        public readonly array $roots,
        public readonly array $progress,
    ) {
    }
}
