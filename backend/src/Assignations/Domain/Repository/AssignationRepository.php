<?php

declare(strict_types=1);

namespace App\Assignations\Domain\Repository;

use App\Assignations\Domain\Model\Assignation;

interface AssignationRepository
{
    public function find(string $assignationsId): ?Assignation;

    /** The assignation of a questionnaire (a questionnaire is assigned to one organization only, PRD §6.14). */
    public function findByQuestionnaire(string $questionnaireId): ?Assignation;

    /**
     * @param string|null $customerId null = every account (Admin)
     *
     * @return list<Assignation> newest first
     */
    public function page(?string $customerId, ?string $type, int $offset, int $limit): array;

    public function count(?string $customerId, ?string $type): int;

    /** @return list<Assignation> */
    public function listByProject(string $projectId): array;

    /**
     * Active follow-ups not completed and not reminded on $today (the reminder run, PRD §7.13).
     *
     * @return list<Assignation>
     */
    public function activeFollowUps(): array;

    public function add(Assignation $assignation): void;

    public function remove(Assignation $assignation): void;
}
