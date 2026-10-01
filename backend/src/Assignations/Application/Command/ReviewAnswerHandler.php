<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Error\NotAFollowUp;
use App\Assignations\Domain\FollowUpRules;
use App\Responses\Application\Command\RecordReview;
use App\Responses\Application\Query\SessionQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ReviewAnswerHandler
{
    public function __construct(
        private readonly OwnedAssignations $owned,
        private readonly FollowUpStatus $followUps,
        private readonly SessionQueries $sessions,
        private readonly CommandBus $commands,
    ) {
    }

    public function __invoke(ReviewAnswer $command): void
    {
        $assignation = $this->owned->get($command->caller, $command->assignationsId);
        if (!$assignation->isFollowUp()) {
            throw new NotAFollowUp();
        }
        FollowUpRules::assertReviewable($this->followUps->of($assignation));
        $session = $this->sessions->find((string) $assignation->sharedSessionId())
            ?? throw new NotFound('QUESTION_NOT_FOUND', 'That question does not exist.');
        $found = false;
        foreach ($session->questions() as $question) {
            if ((string) ($question['id'] ?? '') === $command->questionId && Questions::isAnswerable($question)) {
                $found = true;
            }
        }
        if (!$found) {
            throw new NotFound('QUESTION_NOT_FOUND', 'That question does not exist.');
        }
        $comment = null === $command->comment ? null : trim($command->comment);
        $this->commands->dispatch(new RecordReview($session->id(), $command->questionId, $command->status, '' === $comment ? null : $comment, $session->attempt()));
    }
}
