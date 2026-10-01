<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Creates a project of one organization with its follow-up assignations (PRD §8.9 POST /projects). Returns the
 * project id.
 *
 *     $id = $commands->dispatch(new CreateProject($caller, $organizationId, 'Q4 audits', null, '2026-12-15', [$a1, $a2]));
 *
 * The handler checks the organization (404 ORGANIZATION_NOT_FOUND, also another account's) and each assignation
 * (ProjectAssignationSet). It does NOT run the write-permission check: the caller does first ($caller->canWrite()).
 */
final class CreateProject
{
    /** @param list<string> $assignationIds duplicates are ignored */
    public function __construct(
        public readonly Caller $caller,
        public readonly string $organizationId,
        public readonly string $name,
        public readonly ?string $description,
        public readonly string $dueDate,
        public readonly array $assignationIds = [],
    ) {
    }
}
