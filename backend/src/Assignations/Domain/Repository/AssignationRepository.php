<?php

namespace App\Assignations\Domain\Repository;

use App\Assignations\Domain\Model\Assignation;

interface AssignationRepository
{
    public function find(string $assignationsId): ?Assignation;

    /** The assignation of a questionnaire (a questionnaire is assigned to one organization only, PRD §6.14). */
    public function findByQuestionnaire(string $questionnaireId): ?Assignation;

    /** @return list<Assignation> every assignation of a questionnaire (all of one organization, PRD §6.14) */
    public function listByQuestionnaire(string $questionnaireId): array;

    /**
     * @param string|null $customerId      null = every account (Admin)
     * @param string|null $questionnaireId only the assignations of that questionnaire
     *
     * @return list<Assignation> newest first
     */
    public function page(?string $customerId, ?string $type, int $offset, int $limit, ?string $questionnaireId = null): array;

    public function count(?string $customerId, ?string $type, ?string $questionnaireId = null): int;

    /** @return list<Assignation> */
    public function listByProject(string $projectId): array;

    /**
     * The assignations of several projects in one query, oldest first in each.
     *
     * @param list<string> $projectIds
     *
     * @return array<string, list<Assignation>> by project id
     */
    public function listByProjects(array $projectIds): array;

    /** @return list<Assignation> the organization's follow-ups, oldest first */
    public function followUpsOfOrganization(string $organizationId): array;

    /**
     * Active follow-ups not completed and not reminded on $today (the reminder run, PRD §7.13).
     *
     * @return list<Assignation>
     */
    public function activeFollowUps(): array;

    public function add(Assignation $assignation): void;

    public function remove(Assignation $assignation): void;
}
