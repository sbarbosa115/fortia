<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Deletes a project (PRD §8.9 DELETE /projects/{id}): its assignations are unlinked, never deleted (§7.12).
 * 404 PROJECT_NOT_FOUND for another account's project (unless Admin). The caller checks write permission.
 */
final class DeleteProject
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $projectId,
    ) {
    }
}
