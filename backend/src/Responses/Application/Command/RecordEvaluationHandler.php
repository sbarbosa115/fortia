<?php

namespace App\Responses\Application\Command;

use App\Responses\Domain\Error\QuestionNotFound;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\SessionAnswers;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RecordEvaluationHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly Clock $clock,
    ) {
    }

    /** @return array<string, mixed> the question as stored */
    public function __invoke(RecordEvaluation $command): array
    {
        $session = $this->sessions->get($command->sessionId);
        $questions = SessionAnswers::evaluated($session->questions(), $command->questionId, $command->passed, $command->improvementMessage, $command->flaggedAnswer);
        $session->answer($questions, $this->clock->now());
        foreach ($questions as $question) {
            if ((string) ($question['id'] ?? '') === $command->questionId) {
                return $question;
            }
        }

        throw new QuestionNotFound();
    }
}
