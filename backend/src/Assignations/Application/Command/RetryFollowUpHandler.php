<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Error\NotAFollowUp;
use App\Assignations\Domain\FollowUpRules;
use App\Assignations\Domain\Model\Assignation;
use App\Responses\Application\Command\StartSession;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RetryFollowUpHandler
{
    public function __construct(
        private readonly OwnedAssignations $owned,
        private readonly FollowUpStatus $followUps,
        private readonly CommandBus $commands,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{attempt: int, session_id: string, rejected: int} */
    public function __invoke(RetryFollowUp $command): array
    {
        $assignation = $this->owned->get($command->caller, $command->assignationsId);
        if (!$assignation->isFollowUp()) {
            throw new NotAFollowUp();
        }
        $progress = $this->followUps->of($assignation);
        FollowUpRules::assertRetryable($progress);

        $sessionId = (string) $this->commands->dispatch(new StartSession(
            $assignation->questionnaireId(),
            $assignation->assignationsId(),
            null,
            Assignation::FOLLOW_UP,
            \count($assignation->attempts()) + 1,
            $assignation->sharedSessionId(),
        ));
        $attempt = $assignation->startAttempt($sessionId, $this->clock->now());

        return ['attempt' => $attempt, 'session_id' => $sessionId, 'rejected' => $progress->rejected];
    }
}
