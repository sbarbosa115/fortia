<?php

namespace App\Responses\Application\Command;

use App\Jobs\Application\Jobs;
use App\Responses\Application\Job\AnswerEvaluationJob;
use App\Responses\Application\RespondentAccess;
use App\Responses\Domain\Error\QuestionNotFound;
use App\Responses\Domain\Repository\SessionRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class EvaluateAnswerHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly Jobs $jobs,
    ) {
    }

    public function __invoke(EvaluateAnswer $command): string
    {
        $session = $this->sessions->get($command->sessionId);
        RespondentAccess::check($session, $command->claims);
        if (!\in_array($command->questionId, array_map(static fn (array $q): string => (string) ($q['id'] ?? ''), $session->questions()), true)) {
            throw new QuestionNotFound();
        }

        return $this->jobs->start(AnswerEvaluationJob::TYPE, [
            'session_id' => $session->sessionId(),
            'question_id' => $command->questionId,
            'question' => $command->question,
        ], $session->customerId());
    }
}
