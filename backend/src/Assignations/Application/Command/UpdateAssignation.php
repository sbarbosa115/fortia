<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Changes an assignation (PRD §8.8 PUT /assignations/{id}): only the fields sent, with the names of
 * CreateAssignation except `type`, which never changes. `due_date: null` clears it. Another account's assignation is
 * 404 ASSIGNATION_NOT_FOUND.
 */
final class UpdateAssignation
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public readonly Caller $caller,
        public readonly string $assignationsId,
        public readonly array $fields,
    ) {
    }
}
