<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Model\Project;
use App\Assignations\Domain\Repository\ProjectRepository;
use App\Organizations\Application\Query\OrganizationQueries;
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
        private readonly OrganizationQueries $organizations,
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
        // The project belongs to its organization's account (an Admin may create one for any account).
        $project = new Project($id, (string) $organization['customer_id'], $command->organizationId, trim($command->name), $command->dueDate, $now);
        $project->change(trim($command->name), self::description($command->description), $command->dueDate, $now);
        $this->projects->add($project);
        $this->assignations->replace($id, $assignations, $now);

        return $id;
    }

    public static function description(?string $description): ?string
    {
        $description = null === $description ? null : trim($description);

        return '' === $description ? null : $description;
    }
}
