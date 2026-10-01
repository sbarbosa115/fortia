<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\ProjectNotFound;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteProjectHandler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectAssignationSet $assignations,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeleteProject $command): void
    {
        $project = $this->projects->find($command->projectId);
        if (null === $project || !$command->caller->owns($project->customerId())) {
            throw new ProjectNotFound();
        }
        $this->assignations->unlinkAll($project->projectId(), $this->clock->now());
        $this->projects->remove($project);
    }
}
