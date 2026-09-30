<?php

namespace App\Responses\Application\Command;

use App\Responses\Application\ResultComputer;
use App\Responses\Application\SessionCompletion;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Responses\Domain\Repository\SessionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RecordRecommendationHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly ResultComputer $results,
        private readonly SessionCompletion $completion,
    ) {
    }

    public function __invoke(RecordRecommendation $command): void
    {
        $session = $this->sessions->get($command->sessionId);
        if (QuestionnaireSession::COMPLETED === $session->status()) {
            return;
        }
        $context = $this->results->contextOf($session);
        $this->completion->complete($session, $context->rootQuestionnaireId(), $context->finalStage, ['type' => 'default'], $command->products);
    }
}
