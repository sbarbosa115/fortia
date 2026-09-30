<?php

declare(strict_types=1);

namespace App\Organizations\Infrastructure\Persistence;

use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Repository\OrganizationRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Organization> */
final class DoctrineOrganizationRepository extends DoctrineRepository implements OrganizationRepository
{
    protected function entityClass(): string
    {
        return Organization::class;
    }

    public function find(string $organizationId): ?Organization
    {
        return $this->findEntity($organizationId);
    }

    public function findByDomain(string $domainEmail): ?Organization
    {
        return $this->repository()->findOneBy(['domainEmail' => mb_strtolower($domainEmail)]);
    }

    public function listFor(?string $customerId): array
    {
        $criteria = null === $customerId ? [] : ['customerId' => $customerId];

        return array_values($this->repository()->findBy($criteria, ['createdAt' => 'DESC']));
    }

    public function add(Organization $organization): void
    {
        $this->persist($organization);
    }

    public function remove(Organization $organization): void
    {
        $this->delete($organization);
    }
}
