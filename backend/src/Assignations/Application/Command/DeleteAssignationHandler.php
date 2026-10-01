<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Application\OwnedAssignations;
use App\Assignations\Domain\Event\AssignationDeleted;
use App\Assignations\Domain\Repository\AssignationAnswerRepository;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteAssignationHandler
{
    public function __construct(
        private readonly OwnedAssignations $owned,
        private readonly AssignationRepository $assignations,
        private readonly AssignationAnswerRepository $answers,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(DeleteAssignation $command): void
    {
        $assignation = $this->owned->get($command->caller, $command->assignationsId);
        $this->answers->removeByAssignation($assignation->assignationsId());
        $this->assignations->remove($assignation);
        $this->events->publish(AssignationDeleted::of($assignation->customerId(), $assignation->assignationsId()));
    }
}
