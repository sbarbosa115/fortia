<?php

namespace App\Assignations\Application;

use App\Assignations\Application\Command\RecordReminder;
use App\Assignations\Application\Command\ReminderOutcome;
use App\Assignations\Application\Port\AssignationMailer;
use App\Assignations\Domain\ReminderDigest;
use App\Assignations\Domain\ReminderTiming;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\DomainError;
use Psr\Log\LoggerInterface;

/**
 * The daily reminder run (PRD §7.13, every day at 13:00 UTC): every active follow-up that is not complete and was not
 * reminded today (UTC) is due a reminder. Each person gets one email: the usual reminder when they have one pending
 * follow-up in the account, or one digest listing them all when they have several (ReminderDigest). Then, for each
 * follow-up whose respondents were all reached, the root users get its status and the day is marked (RecordReminder,
 * its own transaction). One failure (logged, counted as skipped) never stops the others; a follow-up with an
 * address that failed is not marked, so the next run tries again.
 *
 * @phpstan-type Summary array{sent: int, skipped: int, recipients: int}
 */
final class DailyReminders
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly ReminderPlans $plans,
        private readonly AssignationMailer $mailer,
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
        /** @var array<string, ReminderPlan> $due */
        $due = [];
        foreach ($this->assignations->activeFollowUps() as $assignation) {
            if (ReminderTiming::remindedToday($assignation->lastReminderSentAt(), $now)) {
                continue;
            }
            try {
                $due[$assignation->assignationsId()] = $this->plans->of($assignation);
            } catch (DomainError $e) {
                $this->logger->info('Daily reminder skipped', ['assignation_id' => $assignation->assignationsId(), 'reason' => $e->errorCode()]);
                ++$summary['skipped'];
            } catch (\Throwable $e) {
                $this->logger->error('Daily reminder failed', ['assignation_id' => $assignation->assignationsId(), 'error' => $e->getMessage()]);
                ++$summary['skipped'];
            }
        }

        $failed = $this->remindRespondents($due);
        foreach ($due as $id => $plan) {
            if (isset($failed[$id])) {
                ++$summary['skipped'];
                continue;
            }
            try {
                $outcome = $this->commands->dispatch(new RecordReminder($id, \count($plan->recipients)));
            } catch (\Throwable $e) {
                $this->logger->error('Daily reminder failed', ['assignation_id' => $id, 'error' => $e->getMessage()]);
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

    /**
     * One email per person and account: the usual reminder for one pending follow-up, a digest for several.
     *
     * @param array<string, ReminderPlan> $due
     *
     * @return array<string, true> the follow-ups with a respondent who was not reached
     */
    private function remindRespondents(array $due): array
    {
        $groups = ReminderDigest::group(array_values(array_map(static fn (ReminderPlan $plan): array => [
            'assignations_id' => $plan->view['assignations_id'],
            'customer_id' => $plan->view['customer_id'],
            'name' => $plan->view['name'],
            'due_date' => $plan->view['due_date'],
            'recipients' => $plan->recipients,
        ], $due)));
        $failed = [];
        foreach ($groups as $group) {
            $plans = array_map(static fn (string $id): ReminderPlan => $due[$id], $group['assignation_ids']);
            try {
                if (1 === \count($plans)) {
                    $this->mailer->sendReminder($plans[0]->view, [$group['email']], $plans[0]->locale, $plans[0]->timing);
                } else {
                    $this->mailer->sendReminderDigest($group['email'], array_map(static fn (ReminderPlan $plan): array => ['assignation' => $plan->view, 'timing' => $plan->timing], $plans), $plans[0]->locale);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Reminder not sent', ['assignation_ids' => $group['assignation_ids'], 'error' => $e->getMessage()]);
                foreach ($group['assignation_ids'] as $id) {
                    $failed[$id] = true;
                }
            }
        }

        return $failed;
    }
}
