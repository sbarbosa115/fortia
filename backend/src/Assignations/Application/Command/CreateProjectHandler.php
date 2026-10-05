<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Event\AssignationCreated;
use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProjectHandler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly ProjectAssignationSet $assignations,
        private readonly AssignationRepository $assignationRepository,
        private readonly ProjectFollowUps $followUps,
        private readonly OrganizationQueries $organizations,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(CreateProject $command): string
    {
        $organization = $this->organizations->find($command->organizationId);
        if (null === $organization || !$command->caller->owns((string) $organization['customer_id'])) {
            throw new NotFound('ORGANIZATION_NOT_FOUND', 'Organization not found.');
        }
        $assignations = $this->assignations->resolve($command->caller, $command->organizationId, null, $command->assignationIds);

        $now = $this->clock->now();
        $id = Ids::uuid4();
        $customerId = (string) $organization['customer_id'];
        // Every questionnaire is checked before anything is added, so a refusal leaves nothing behind.
        $created = $this->followUps->build($command->caller, $customerId, $command->organizationId, $command->questionnaireIds, $command->dueDate, $command->registrationTitle, $now);

        // The project belongs to its organization's account (an Admin may create one for any account).
        $project = new Project($id, $customerId, $command->organizationId, trim($command->name), $command->dueDate, $now);
        $project->change(trim($command->name), self::description($command->description), $command->dueDate, $now);
        $this->projects->add($project);
        foreach ($created as $assignation) {
            $this->assignationRepository->add($assignation);
        }
        $this->assignations->replace($id, [...$assignations, ...$created], $now);
        foreach ($assignations as $assignation) {
            $assignation->requireReview($command->requiresReview, $now);
        }
        $reviewed = null === $command->reviewQuestionnaireIds ? null : array_map('strtolower', $command->reviewQuestionnaireIds);
        foreach ($created as $assignation) {
            $assignation->requireReview(null === $reviewed ? $command->requiresReview : \in_array($assignation->questionnaireId(), $reviewed, true), $now);
        }
        $project->requireReview(ProjectReview::any([...$assignations, ...$created], $command->requiresReview), $now);
        foreach ($created as $assignation) {
            $this->events->publish(AssignationCreated::of($customerId, $assignation->assignationsId()));
        }

        return $id;
    }

    public static function description(?string $description): ?string
    {
        $description = null === $description ? null : trim($description);

        return '' === $description ? null : $description;
    }
}
