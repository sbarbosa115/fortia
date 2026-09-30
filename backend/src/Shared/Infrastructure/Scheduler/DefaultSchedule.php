<?php

namespace App\Shared\Infrastructure\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The "default" schedule the worker consumes (scheduler_default). A context adds its recurring work with
 * #[AsCronTask('0 13 * * *')] on its message handler (e.g. the daily reminders, PRD §7.13), not in this file.
 */
#[AsSchedule]
final class DefaultSchedule implements ScheduleProviderInterface
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true);
    }
}
