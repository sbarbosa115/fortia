<?php

declare(strict_types=1);

namespace App\Assignations\Domain\Repository;

use App\Assignations\Domain\Model\AssignationAnswer;

interface AssignationAnswerRepository
{
    public function find(string $assignationsId, string $organizationUserId): ?AssignationAnswer;

    /** @return list<AssignationAnswer> */
    public function listByAssignation(string $assignationsId): array;

    public function add(AssignationAnswer $answer): void;
}
