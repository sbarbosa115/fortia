<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\Query\FollowUpStatus;
use App\Assignations\Domain\Audience;
use App\Assignations\Domain\Error\AssignationNotFound;
use App\Assignations\Domain\Error\FollowUpCompleted;
use App\Assignations\Domain\Error\MissingIdentifier;
use App\Assignations\Domain\Error\NotInAudience;
use App\Assignations\Domain\Error\UserNotFound;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\RespondentLookup;
use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Responses\Application\Command\StartSession;
use App\Responses\Application\Query\SessionQueries;
use App\Responses\Application\Query\SessionView;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\RespondentTokens;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartRespondentSessionHandler
{
    public function __construct(
        private readonly AssignationRepository $assignations,
        private readonly FollowUpStatus $followUps,
        private readonly OrganizationQueries $organizations,
        private readonly SessionQueries $sessions,
        private readonly PlanGate $gate,
        private readonly CommandBus $commands,
        private readonly RespondentTokens $tokens,
        private readonly Clock $clock,
    ) {
    }

    /** @return array{token: string, session_id: string} */
    public function __invoke(StartRespondentSession $command): array
    {
        $assignation = $this->assignations->find($command->assignationsId);
        if (null === $assignation || !$assignation->isActive()) {
            throw new AssignationNotFound($command->assignationsId);
        }
        $this->gate->featureForAccount($assignation->customerId(), Features::ASSIGNATIONS);
        if ($assignation->isFollowUp() && $this->followUps->of($assignation)->ended) {
            throw new FollowUpCompleted();
        }
        if (!RespondentLookup::hasIdentifier($command->email, $command->phone)) {
            throw new MissingIdentifier();
        }
        $member = RespondentLookup::find($this->organizations->membersOf($assignation->organizationId()), $command->email, $command->phone)
            ?? throw new UserNotFound();
        if (!Audience::includes($assignation->audience(), $member)) {
            throw new NotInAudience();
        }
        $this->gate->capacityForAccount($assignation->customerId(), Features::RESPONSES);

        $memberId = (string) $member['organization_user_id'];
        $sessionId = $assignation->isFollowUp() ? $this->sharedSession($assignation) : $this->ownSession($assignation, $memberId);

        return ['token' => $this->tokens->issue($assignation->assignationsId(), $memberId, $sessionId), 'session_id' => $sessionId];
    }

    /** All members write to the follow-up's one session; the first login opens it as attempt 1. */
    private function sharedSession(Assignation $assignation): string
    {
        $sessionId = $assignation->sharedSessionId();
        if (null !== $sessionId && null !== $this->sessions->find($sessionId)) {
            return $sessionId;
        }
        $sessionId = (string) $this->commands->dispatch(new StartSession(
            $assignation->questionnaireId(),
            $assignation->assignationsId(),
            null,
            Assignation::FOLLOW_UP,
            \count($assignation->attempts()) + 1,
        ));
        $assignation->startAttempt($sessionId, $this->clock->now());

        return $sessionId;
    }

    /** A default assignation: the member's open session again, or a new one (their next attempt). */
    private function ownSession(Assignation $assignation, string $memberId): string
    {
        $mine = array_values(array_filter(
            $this->sessions->ofAssignation($assignation->assignationsId()),
            static fn (SessionView $s): bool => $memberId === ($s->data['organization_user_id'] ?? null) && $s->questionnaireId() === $assignation->questionnaireId(),
        ));
        foreach ($mine as $session) {
            if (!$session->isEnded()) {
                return $session->id();
            }
        }

        return (string) $this->commands->dispatch(new StartSession(
            $assignation->questionnaireId(),
            $assignation->assignationsId(),
            $memberId,
            null,
            \count($mine) + 1,
        ));
    }
}
