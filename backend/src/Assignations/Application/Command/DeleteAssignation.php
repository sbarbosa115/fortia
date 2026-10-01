<?php

namespace App\Assignations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * Deletes an assignation and its member → session index (PRD §8.8 DELETE). Its sessions are the respondents'
 * answers and stay.
 */
final class DeleteAssignation
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $assignationsId,
    ) {
    }
}
