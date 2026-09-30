<?php

namespace App\Organizations\Application\Port;

/**
 * What else points at an organization (D2): its assignations and projects, which live in the Assignations context.
 * Deleting an organization that still has any is refused (409 ORGANIZATION_HAS_ASSIGNATIONS).
 */
interface OrganizationDependents
{
    /** @return array{assignations: int, projects: int} */
    public function count(string $organizationId): array;
}
