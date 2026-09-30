<?php

declare(strict_types=1);

namespace App\Organizations\Domain\Repository;

use App\Organizations\Domain\Model\Organization;

interface OrganizationRepository
{
    public function find(string $organizationId): ?Organization;

    public function findByDomain(string $domainEmail): ?Organization;

    /**
     * @param string|null $customerId null = every account (Admin)
     *
     * @return list<Organization> newest first
     */
    public function listFor(?string $customerId): array;

    public function add(Organization $organization): void;

    public function remove(Organization $organization): void;
}
