<?php

namespace App\Organizations\Application\Command;

use App\Organizations\Application\Port\OrganizationDependents;
use App\Organizations\Domain\Error\OrganizationHasAssignations;
use App\Organizations\Domain\Error\OrganizationNotFound;
use App\Organizations\Domain\Event\OrganizationDeleted;
use App\Organizations\Domain\Repository\OrganizationRepository;
use App\Organizations\Domain\Repository\OrganizationUserRepository;
use App\Shared\Application\Bus\EventBus;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteOrganizationHandler
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationUserRepository $members,
        private readonly OrganizationDependents $dependents,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(DeleteOrganization $command): void
    {
        $organization = $this->organizations->find($command->organizationId);
        if (null === $organization || !$command->caller->owns($organization->customerId())) {
            throw new OrganizationNotFound();
        }
        $dependents = $this->dependents->count($organization->organizationId());
        if ($dependents['assignations'] > 0 || $dependents['projects'] > 0) {
            throw new OrganizationHasAssignations($dependents['assignations'], $dependents['projects']);
        }

        $removed = $this->members->removeByOrganization($organization->organizationId());
        $this->organizations->remove($organization);
        $this->events->publish(OrganizationDeleted::of($organization->customerId(), $organization->organizationId(), $removed));
    }
}
