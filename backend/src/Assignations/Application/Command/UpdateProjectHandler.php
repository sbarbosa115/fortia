<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\ProjectNotFound;
use App\Assignations\Domain\Event\AssignationCreated;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Shared\Application\Bus\EventBus;
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
        private readonly ProjectFollowUps $followUps,
        private readonly EventBus $events,
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
        $dueDate = \array_key_exists('due_date', $fields) ? (string) $fields['due_date'] : (string) $project->dueDate();
        $members = null;
        if (\array_key_exists('assignation_ids', $fields)) {
            /** @var list<string> $ids */
            $ids = (array) $fields['assignation_ids'];
            $members = $this->assignations->resolve($command->caller, $project->organizationId(), $project->projectId(), $ids);
        }
        // questionnaire_ids: each one becomes a new follow-up of the organization that joins the project, all of them
        // checked before anything is added.
        /** @var list<string> $questionnaireIds */
        $questionnaireIds = (array) ($fields['questionnaire_ids'] ?? []);
        $registrationTitle = isset($fields['registration_title']) ? (string) $fields['registration_title'] : null;
        $created = $this->followUps->build($command->caller, $project->customerId(), $project->organizationId(), $questionnaireIds, $dueDate, $registrationTitle, $now);
        if (null !== $members || [] !== $created) {
            $members ??= $this->assignationRepository->listByProject($project->projectId());
            foreach ($created as $assignation) {
                $this->assignationRepository->add($assignation);
            }
            $this->assignations->replace($project->projectId(), [...$members, ...$created], $now);
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
        // A new follow-up goes to review when its questionnaire is in review_questionnaire_ids; without that list,
        // when requires_review says so (not sent either: it does).
        $reviewedQuestionnaires = \array_key_exists('review_questionnaire_ids', $fields)
            ? array_map(static fn (mixed $id): string => strtolower((string) $id), (array) $fields['review_questionnaire_ids'])
            : null;
        foreach ($created as $assignation) {
            $assignation->requireReview(null === $reviewedQuestionnaires ? (bool) ($fields['requires_review'] ?? true) : \in_array($assignation->questionnaireId(), $reviewedQuestionnaires, true), $now);
        }
        $fallback = \array_key_exists('requires_review', $fields) ? (bool) $fields['requires_review'] : $project->requiresReview();
        $project->requireReview(ProjectReview::any([...$members, ...$created], $fallback), $now);
        $project->change(
            \array_key_exists('name', $fields) ? trim((string) $fields['name']) : $project->name(),
            \array_key_exists('description', $fields) ? CreateProjectHandler::description(null === $fields['description'] ? null : (string) $fields['description']) : $project->description(),
            $dueDate,
            $now,
        );
        if (\array_key_exists('due_date', $fields)) {
            // The console's assignation is the project: its questionnaires are due on its deadline.
            foreach ($this->assignationRepository->listByProject($project->projectId()) as $assignation) {
                $assignation->moveDueDate((string) $fields['due_date'], $now);
            }
        }
        foreach ($created as $assignation) {
            $this->events->publish(AssignationCreated::of($project->customerId(), $assignation->assignationsId()));
        }
    }
}
