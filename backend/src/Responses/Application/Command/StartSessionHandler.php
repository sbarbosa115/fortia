<?php

namespace App\Responses\Application\Command;

use App\Questionnaires\Application\Query\QuestionnaireQueries;
use App\Responses\Application\ChainStages;
use App\Responses\Application\SessionDocument;
use App\Responses\Domain\Event\QuestionnaireSessionCreated;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\SessionAnswers;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartSessionHandler
{
    public function __construct(
        private readonly QuestionnaireQueries $questionnaires,
        private readonly SessionRepository $sessions,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(StartSession $command): string
    {
        $questionnaire = $this->questionnaires->find($command->questionnaireId)
            ?? throw new NotFound('QUESTIONNAIRE_NOT_FOUND', 'The questionnaire does not exist.');

        $questions = null;
        if (null !== $command->carryOverFrom) {
            $questions = SessionAnswers::carryOverForRetry($this->sessions->get($command->carryOverFrom)->questions());
        }

        $session = new QuestionnaireSession(
            Ids::uuid4(),
            $questionnaire->id(),
            $questionnaire->customerId(),
            SessionDocument::of($questionnaire, $questions),
            $this->clock->now(),
        );
        $rootId = ChainStages::rootIdOf($questionnaire);
        $flow = $this->questionnaires->flowOf($questionnaire->id()) ?? $this->questionnaires->flowOf($rootId);
        $session->attachFlow($flow?->id());
        if (null !== $command->assignationsId) {
            $session->bindToAssignation($command->assignationsId, $command->organizationUserId, $command->assignationType, $command->attempt);
        }
        $this->sessions->add($session);
        $this->events->publish(QuestionnaireSessionCreated::of($session->customerId(), $session->sessionId(), $session->questionnaireId()));

        return $session->sessionId();
    }
}
