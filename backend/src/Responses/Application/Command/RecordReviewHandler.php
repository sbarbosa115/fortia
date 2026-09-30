<?php

namespace App\Responses\Application\Command;

use App\Responses\Domain\Error\QuestionLocked;
use App\Responses\Domain\Error\QuestionNotFound;
use App\Responses\Domain\Repository\SessionRepository;
use App\Responses\Domain\SessionAnswers;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RecordReviewHandler
{
    public function __construct(
        private readonly SessionRepository $sessions,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(RecordReview $command): void
    {
        $session = $this->sessions->get($command->sessionId);
        $question = null;
        foreach ($session->questions() as $q) {
            if ((string) ($q['id'] ?? '') === $command->questionId) {
                $question = $q;
            }
        }
        if (null === $question) {
            throw new QuestionNotFound();
        }
        if (SessionAnswers::isLocked($question)) {
            throw new QuestionLocked();
        }

        $session->answer(SessionAnswers::review($session->questions(), $command->questionId, [
            'status' => $command->status,
            'comment' => $command->comment,
            'reviewed_at' => Iso::datetime($this->clock->now()),
            'attempt' => $command->attempt,
        ]), $this->clock->now());
    }
}
