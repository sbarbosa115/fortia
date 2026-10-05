<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\ProjectNotFound;
use App\Assignations\Domain\Repository\AssignationRepository;
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
        private readonly AssignationRepository $assignationRepository,
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
        $members = null;
        if (\array_key_exists('assignation_ids', $fields)) {
            /** @var list<string> $ids */
            $ids = (array) $fields['assignation_ids'];
            $members = $this->assignations->resolve($command->caller, $project->organizationId(), $project->projectId(), $ids);
            $this->assignations->replace($project->projectId(), $members, $now);
        }
        // Review is per follow-up: requires_review sets it on all of them, review_assignation_ids one by one.
        $members ??= $this->assignationRepository->listByProject($project->projectId());
        if (\array_key_exists('requires_review', $fields)) {
            foreach ($members as $assignation) {
                $assignation->requireReview((bool) $fields['requires_review'], $now);
            }
        }
        if (\array_key_exists('review_assignation_ids', $fields)) {
            $reviewed = array_map(static fn (mixed $id): string => strtolower((string) $id), (array) $fields['review_assignation_ids']);
            foreach ($members as $assignation) {
                $assignation->requireReview(\in_array($assignation->assignationsId(), $reviewed, true), $now);
            }
        }
        $fallback = \array_key_exists('requires_review', $fields) ? (bool) $fields['requires_review'] : $project->requiresReview();
        $project->requireReview(ProjectReview::any($members, $fallback), $now);
        $project->change(
            \array_key_exists('name', $fields) ? trim((string) $fields['name']) : $project->name(),
            \array_key_exists('description', $fields) ? CreateProjectHandler::description(null === $fields['description'] ? null : (string) $fields['description']) : $project->description(),
            \array_key_exists('due_date', $fields) ? (string) $fields['due_date'] : (string) $project->dueDate(),
            $now,
        );
        if (\array_key_exists('due_date', $fields)) {
            // The console's assignation is the project: its questionnaires are due on its deadline.
            foreach ($this->assignationRepository->listByProject($project->projectId()) as $assignation) {
                $assignation->moveDueDate((string) $fields['due_date'], $now);
            }
        }
    }
}
