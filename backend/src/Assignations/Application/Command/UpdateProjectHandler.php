<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\ProjectNotFound;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\Rejected;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateProjectHandler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectAssignationSet $assignations,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(UpdateProject $command): void
    {
        $project = $this->projects->find($command->projectId);
        if (null === $project || !$command->caller->owns($project->customerId())) {
            throw new ProjectNotFound();
        }
        $fields = $command->fields;
        if (\array_key_exists('organization_id', $fields) && strtolower((string) $fields['organization_id']) !== $project->organizationId()) {
            throw new Rejected('VALIDATION_ERROR', 'organization_id: the organization of a project cannot be changed.');
        }

        $now = $this->clock->now();
        if (\array_key_exists('assignation_ids', $fields)) {
            /** @var list<string> $ids */
            $ids = (array) $fields['assignation_ids'];
            $resolved = $this->assignations->resolve($command->caller, $project->organizationId(), $project->projectId(), $ids);
            $this->assignations->replace($project->projectId(), $resolved, $now);
        }
        $project->change(
            \array_key_exists('name', $fields) ? trim((string) $fields['name']) : $project->name(),
            \array_key_exists('description', $fields) ? CreateProjectHandler::description(null === $fields['description'] ? null : (string) $fields['description']) : $project->description(),
            \array_key_exists('due_date', $fields) ? (string) $fields['due_date'] : (string) $project->dueDate(),
            $now,
        );
    }
}
