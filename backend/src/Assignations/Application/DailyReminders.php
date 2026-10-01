<?php

namespace App\Assignations\Application;

use App\Assignations\Application\Command\ReminderOutcome;
use App\Assignations\Application\Command\SendReminder;
use App\Assignations\Domain\ReminderTiming;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Clock;
use Psr\Log\LoggerInterface;

/**
 * The daily reminder run (PRD §7.13, every day at 13:00 UTC): every active follow-up that is not complete and was not
 * reminded today (UTC) gets SendReminder, each in its own transaction, so one failure (logged, counted as skipped)
 * never stops the others.
 *
 * @phpstan-type Summary array{sent: int, skipped: int, recipients: int}
 */
final class DailyReminders
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly CommandBus $commands,
        private readonly LoggerInterface $logger,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return Summary
     *
     * @phpstan-impure
     */
    public function run(): array
    {
        $now = $this->clock->now();
        $summary = ['sent' => 0, 'skipped' => 0, 'recipients' => 0];
        foreach ($this->assignations->activeFollowUps() as $assignation) {
            if (ReminderTiming::remindedToday($assignation->lastReminderSentAt(), $now)) {
                continue;
            }
            try {
                $outcome = $this->commands->dispatch(new SendReminder($assignation->assignationsId()));
            } catch (\Throwable $e) {
                $this->logger->error('Daily reminder failed', ['assignation_id' => $assignation->assignationsId(), 'error' => $e->getMessage()]);
                $outcome = ReminderOutcome::skipped('ERROR');
            }
            if ($outcome instanceof ReminderOutcome && $outcome->sent) {
                ++$summary['sent'];
                $summary['recipients'] += $outcome->recipients;
            } else {
                ++$summary['skipped'];
            }
        }
        $this->logger->info('Daily reminders', $summary);

        return $summary;
    }
}
