<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Application\Port\AssignationMailer;
use App\Assignations\Application\ReminderPlans;
use App\Assignations\Domain\Error\ReminderNotSent;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Mail\MailNotSent;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\DomainError;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SendReminderHandler
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly OwnedAssignations $owned,
        private readonly ReminderPlans $plans,
        private readonly AssignationMailer $mailer,
        private readonly LoggerInterface $logger,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SendReminder $command): ReminderOutcome
    {
        if (null !== $command->caller) {
            return $this->send($this->owned->get($command->caller, $command->assignationsId));
        }
        $assignation = $this->assignations->find($command->assignationsId);
        if (null === $assignation) {
            return ReminderOutcome::skipped('ASSIGNATION_NOT_FOUND');
        }
        try {
            return $this->send($assignation);
        } catch (DomainError $e) {
            $this->logger->info('Daily reminder skipped', ['assignation_id' => $assignation->assignationsId(), 'reason' => $e->errorCode()]);

            return ReminderOutcome::skipped($e->errorCode());
        }
    }

    private function send(Assignation $assignation): ReminderOutcome
    {
        $plan = $this->plans->of($assignation);
        try {
            $this->mailer->sendReminder($plan->view, $plan->recipients, $plan->locale, $plan->timing);
            $this->plans->sendStatus($plan, \count($plan->recipients));
        } catch (MailNotSent $e) {
            $this->logger->error('Reminder not sent', ['assignation_id' => $assignation->assignationsId(), 'error' => $e->getMessage()]);
            throw new ReminderNotSent();
        }
        $assignation->markReminded($this->clock->now());

        return ReminderOutcome::sent(\count($plan->recipients));
    }
}
