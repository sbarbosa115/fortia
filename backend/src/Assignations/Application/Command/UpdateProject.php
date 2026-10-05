<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Changes a project (PRD §8.9 PUT /projects/{id}), partially: $fields holds only what was sent, with the PRD's
 * names — name, description, due_date, requires_review (set on all its follow-ups), review_assignation_ids (its follow-ups that
 * require review; the others do not), assignation_ids (replaces the set) and organization_id (accepted only when
 * it is the project's own: the organization never changes, 400 otherwise).
 *
 * 404 PROJECT_NOT_FOUND for another account's project (unless Admin). The caller checks write permission.
 */
final class UpdateProject
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly Caller $caller,
        public readonly string $projectId,
        public readonly array $fields,
    ) {
    }
}
