<?php

namespace App\Assignations\Application\Command;

use App\Assignations\Domain\Error\AssignationInOtherProject;
use App\Assignations\Domain\Error\AssignationNotFollowUp;
use App\Assignations\Domain\Error\AssignationNotFound;
use App\Assignations\Domain\Error\AssignationOrganizationMismatch;
use App\Assignations\Domain\Model\Assignation;
use App\Assignations\Domain\Repository\AssignationRepository;
use App\Shared\Application\Security\Caller;

/**
 * The assignations of a project (PRD §7.12, §8.9): each one must exist and be the caller's (404
 * ASSIGNATION_NOT_FOUND), be a follow-up (400 ASSIGNATION_NOT_FOLLOW_UP), belong to the project's organization (400
 * ASSIGNATION_ORGANIZATION_MISMATCH) and not to another project (409 ASSIGNATION_IN_OTHER_PROJECT). Setting them
 * replaces the set: the ones left out are unlinked, never deleted.
 */
final class ProjectAssignationSet
{
    public function __construct(private readonly AssignationRepository $assignations)
    {
    }

    /**
     * @param list<string> $assignationIds deduplicated here
     *
     * @return list<Assignation>
     */
    public function resolve(Caller $caller, string $organizationId, ?string $projectId, array $assignationIds): array
    {
        $resolved = [];
        foreach (array_values(array_unique(array_map('strtolower', $assignationIds))) as $id) {
            $assignation = $this->assignations->find($id);
            if (null === $assignation || !$caller->owns($assignation->customerId())) {
                throw new AssignationNotFound($id);
            }
            if (!$assignation->isFollowUp()) {
                throw new AssignationNotFollowUp($id);
            }
            if ($assignation->organizationId() !== $organizationId) {
                throw new AssignationOrganizationMismatch($id);
            }
            $current = $assignation->projectId();
            if (null !== $current && $current !== $projectId) {
                throw new AssignationInOtherProject($id, $current);
            }
            $resolved[] = $assignation;
        }

        return $resolved;
    }

    /** @param list<Assignation> $assignations the whole new set (resolve() first) */
    public function replace(string $projectId, array $assignations, \DateTimeImmutable $at): void
    {
        $keep = array_map(static fn (Assignation $a): string => $a->assignationsId(), $assignations);
        foreach ($this->assignations->listByProject($projectId) as $current) {
            if (!\in_array($current->assignationsId(), $keep, true)) {
                $current->joinProject(null, $at);
            }
        }
        foreach ($assignations as $assignation) {
            if ($assignation->projectId() !== $projectId) {
                $assignation->joinProject($projectId, $at);
            }
        }
    }

    public function unlinkAll(string $projectId, \DateTimeImmutable $at): void
    {
        foreach ($this->assignations->listByProject($projectId) as $assignation) {
            $assignation->joinProject(null, $at);
        }
    }
}
