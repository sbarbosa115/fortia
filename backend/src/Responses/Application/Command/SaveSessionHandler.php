<?php

namespace App\Responses\Application\Command;

use App\Responses\Application\RespondentAccess;
use App\Responses\Domain\Error\FollowUpCompleted;
use App\Responses\Domain\Event\QuestionnaireSessionUpdated;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\SessionAnswers;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveSessionHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveSession $command): void
    {
        $session = $this->sessions->get($command->sessionId);
        RespondentAccess::check($session, $command->claims);
        if ($session->isFollowUp() && $session->isEnded()) {
            throw new FollowUpCompleted();
        }
        if ($session->isEnded()) {
            // A submitted session keeps the answers its result was computed from.
            return;
        }

        $session->answer(SessionAnswers::apply($session->questions(), $command->questions, $session->isFollowUp()), $this->clock->now());
        $this->events->publish(QuestionnaireSessionUpdated::of($session->customerId(), $session->sessionId(), $session->questionnaireId()));
    }
}
