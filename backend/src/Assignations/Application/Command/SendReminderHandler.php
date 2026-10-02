<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\AssignationMailContext;
use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Application\Port\AssignationMailer;
use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\FollowUpCompleted;
use App\Assignations\Domain\Error\NoRecipients;
use App\Assignations\Domain\Error\NotAFollowUp;
use App\Assignations\Domain\Error\ReminderNotSent;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\ReminderTiming;
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
        private readonly FollowUpStatus $followUps,
        private readonly AssignationMailContext $context,
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
        if (!$assignation->isFollowUp()) {
            throw new NotAFollowUp();
        }
        $progress = $this->followUps->of($assignation);
        if ($progress->ended) {
            throw new FollowUpCompleted();
        }
        $recipients = Audience::emails($assignation->audience(), $this->context->members($assignation));
        if ([] === $recipients) {
            throw new NoRecipients();
        }
        $view = $this->context->view($assignation);
        $locale = $this->context->locale($assignation);
        $timing = ReminderTiming::of($view['due_date'], $this->clock->today());
        // An owner who is also a respondent only receives the reminder (§7.13).
        $roots = array_values(array_diff($this->context->rootEmails($assignation), $recipients));
        try {
            $this->mailer->sendReminder($view, $recipients, $locale, $timing);
            if ([] !== $roots) {
                $this->mailer->sendStatus($view, $roots, $locale, $timing, [
                    'completed' => $progress->completed,
                    'total' => $progress->total,
                    'percent' => (int) round($progress->percent()),
                ], \count($recipients));
            }
        } catch (MailNotSent $e) {
            $this->logger->error('Reminder not sent', ['assignation_id' => $assignation->assignationsId(), 'error' => $e->getMessage()]);
            throw new ReminderNotSent();
        }
        $assignation->markReminded($this->clock->now());

        return ReminderOutcome::sent(\count($recipients));
    }
}
