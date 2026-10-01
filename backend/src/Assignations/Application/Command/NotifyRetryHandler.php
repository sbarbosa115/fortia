<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\AssignationMailContext;
use App\Assignations\Application\Port\AssignationMailer;
use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\AssignationNotFound;
use App\Assignations\Domain\Error\RetryEmailNotSent;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Mail\MailNotSent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class NotifyRetryHandler
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly AssignationMailContext $context,
        private readonly AssignationMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(NotifyRetry $command): int
    {
        $assignation = $this->assignations->find($command->assignationsId) ?? throw new AssignationNotFound($command->assignationsId);
        $recipients = Audience::emails($assignation->audience(), $this->context->members($assignation));
        if ([] === $recipients) {
            return 0;
        }
        try {
            $this->mailer->sendRetry($this->context->view($assignation), $recipients, $this->context->locale($assignation), $command->attempt, $command->rejected);
        } catch (MailNotSent $e) {
            $this->logger->error('Retry email not sent', ['assignation_id' => $assignation->assignationsId(), 'error' => $e->getMessage()]);
            throw new RetryEmailNotSent();
        }

        return \count($recipients);
    }
}
