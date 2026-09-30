<?php

declare(strict_types=1);

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
        return array_values($this->repository()->findBy(['organizationId' => $organizationId], ['name' => 'ASC']));
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
}
