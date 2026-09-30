<?php

namespace App\Organizations\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/**
 * 409: D2 — an organization with assignations or projects is not deleted (they would be orphaned: their respondents
 * log in against its members, and a project's organization is immutable). Delete those first.
 */
final class OrganizationHasAssignations extends Conflict
{
    public function __construct(int $assignations, int $projects)
    {
        parent::__construct(
            'ORGANIZATION_HAS_ASSIGNATIONS',
            'This organization has assignations or projects; delete them before deleting the organization.',
            ['assignations' => $assignations, 'projects' => $projects],
        );
    }
}
