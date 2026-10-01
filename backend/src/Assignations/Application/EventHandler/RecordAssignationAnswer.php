<?php

namespace App\Assignations\Application\EventHandler;

use App\Assignations\Domain\Model\AssignationAnswer;
use App\Assignations\Domain\Repository\AssignationAnswerRepository;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Domain\Event\QuestionnaireSessionCompleted;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRD §6.15: when a member submits the first stage of a default assignation, (assignation, member) → session is
 * written (or pointed at the newer session when they answer again). A follow-up's shared session has no member.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class RecordAssignationAnswer
{
    public function __construct(
        private readonly SessionQueries $sessions,
        private readonly AssignationRepository $assignations,
        private readonly AssignationAnswerRepository $answers,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(QuestionnaireSessionCompleted $event): void
    {
        $session = $this->sessions->find($event->sessionId());
        $assignationsId = $session?->data['assignations_id'] ?? null;
        $memberId = $session?->data['organization_user_id'] ?? null;
        if (null === $session || !\is_string($assignationsId) || !\is_string($memberId)) {
            return;
        }
        $assignation = $this->assignations->find($assignationsId);
        if (null === $assignation || $assignation->questionnaireId() !== $session->questionnaireId()) {
            return;
        }
        $answer = $this->answers->find($assignationsId, $memberId);
        if (null === $answer) {
            $this->answers->add(new AssignationAnswer($assignationsId, $memberId, $session->id(), $this->clock->now()));
        } else {
            $answer->repointTo($session->id());
        }
    }
}
