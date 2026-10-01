<?php

namespace App\Assignations\Domain\Repository;

use App\Assignations\Domain\Model\AssignationAnswer;

interface AssignationAnswerRepository
{
    public function find(string $assignationsId, string $organizationUserId): ?AssignationAnswer;

    /** @return list<AssignationAnswer> */
    public function listByAssignation(string $assignationsId): array;

    public function add(AssignationAnswer $answer): void;

    /** Deletes the answers of an assignation (it is being deleted). */
    public function removeByAssignation(string $assignationsId): void;
}
