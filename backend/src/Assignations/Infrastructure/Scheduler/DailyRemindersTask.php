<?php

namespace App\Assignations\Infrastructure\Scheduler;

use App\Assignations\Application\DailyReminders;
use Symfony\Component\Scheduler\Attribute\AsCronTask;

/** PRD §7.13, §11: the follow-up reminders go out every day at 13:00 UTC (the worker consumes scheduler_default). */
#[AsCronTask('0 13 * * *', timezone: 'UTC')]
final class DailyRemindersTask
{
    public function __construct(private readonly DailyReminders $reminders)
    {
    }

    public function __invoke(): void
    {
        $this->reminders->run();
    }
}
