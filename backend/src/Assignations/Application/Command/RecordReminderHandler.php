<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\ReminderPlans;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\DomainError;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RecordReminderHandler
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly ReminderPlans $plans,
        private readonly LoggerInterface $logger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RecordReminder $command): ReminderOutcome
    {
        $assignation = $this->assignations->find($command->assignationsId);
        if (null === $assignation) {
            return ReminderOutcome::skipped('ASSIGNATION_NOT_FOUND');
        }
        try {
            $this->plans->sendStatus($this->plans->of($assignation), $command->reminded);
        } catch (DomainError $e) {
            $this->logger->info('Daily reminder skipped', ['assignation_id' => $command->assignationsId, 'reason' => $e->errorCode()]);

            return ReminderOutcome::skipped($e->errorCode());
        } catch (MailNotSent $e) {
            $this->logger->error('Reminder not sent', ['assignation_id' => $command->assignationsId, 'error' => $e->getMessage()]);

            return ReminderOutcome::skipped('REMINDER_NOT_SENT');
        }
        $assignation->markReminded($this->clock->now());

        return ReminderOutcome::sent($command->reminded);
    }
}
