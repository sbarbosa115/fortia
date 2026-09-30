<?php

namespace App\Organizations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Deletes an organization and its members (PRD §8.7, D2). 404 ORGANIZATION_NOT_FOUND for another account's
 * organization unless the caller is an Admin (D1); 409 ORGANIZATION_HAS_ASSIGNATIONS while assignations or projects
 * still point at it. No plan gate (deletions have none, §7.1), but OrganizationDeleted counts usage (§7.2).
 */
final class DeleteOrganization
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $organizationId,
    ) {
    }
}
