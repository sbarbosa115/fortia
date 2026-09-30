<?php

namespace App\Organizations\Infrastructure\Persistence;

use App\Organizations\Domain\Model\OrganizationUser;
use App\Organizations\Domain\Repository\OrganizationUserRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<OrganizationUser> */
final class DoctrineOrganizationUserRepository extends DoctrineRepository implements OrganizationUserRepository
{
    protected function entityClass(): string
    {
        return OrganizationUser::class;
    }

    public function find(string $organizationUserId): ?OrganizationUser
    {
        return $this->findEntity($organizationUserId);
    }

    public function listByOrganization(string $organizationId): array
    {
        return $this->repository()->findBy(['organizationId' => $organizationId], ['name' => 'ASC']);
    }

    public function listByOrganizations(array $organizationIds): array
    {
        $byOrganization = array_fill_keys($organizationIds, []);
        if ([] === $organizationIds) {
            return $byOrganization;
        }
        foreach ($this->repository()->findBy(['organizationId' => $organizationIds], ['name' => 'ASC']) as $member) {
            $byOrganization[$member->organizationId()][] = $member;
        }

        return $byOrganization;
    }

    public function add(OrganizationUser $member): void
    {
        $this->persist($member);
    }

    public function remove(OrganizationUser $member): void
    {
        $this->delete($member);
    }

    public function prepareReconciliation(array $removed, array $emailChanged): void
    {
        // Bulk DQL runs now, inside the command's transaction; the unit of work still flushes the rest at commit.
        if ([] !== $removed) {
            foreach ($removed as $member) {
                $this->em->detach($member);
            }
            $this->em->createQuery(\sprintf('DELETE FROM %s m WHERE m.organizationUserId IN (:ids)', OrganizationUser::class))
                ->setParameter('ids', array_map(static fn (OrganizationUser $m): string => $m->organizationUserId(), $removed))
                ->execute();
        }
        if ([] !== $emailChanged) {
            $this->em->createQuery(\sprintf('UPDATE %s m SET m.email = NULL WHERE m.organizationUserId IN (:ids)', OrganizationUser::class))
                ->setParameter('ids', array_map(static fn (OrganizationUser $m): string => $m->organizationUserId(), $emailChanged))
                ->execute();
        }
    }

    public function removeByOrganization(string $organizationId): int
    {
        $members = $this->listByOrganization($organizationId);
        foreach ($members as $member) {
            $this->delete($member);
        }

        return \count($members);
    }
}
