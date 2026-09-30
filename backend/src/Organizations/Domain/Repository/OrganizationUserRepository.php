<?php

declare(strict_types=1);

namespace App\Organizations\Domain\Repository;

use App\Organizations\Domain\Model\OrganizationUser;

interface OrganizationUserRepository
{
    public function find(string $organizationUserId): ?OrganizationUser;

    /** @return list<OrganizationUser> ordered by name */
    public function listByOrganization(string $organizationId): array;

    /**
     * Members of many organizations at once (one query for a listing).
     *
     * @param list<string> $organizationIds
     *
     * @return array<string, list<OrganizationUser>> by organization id
     */
    public function listByOrganizations(array $organizationIds): array;

    public function add(OrganizationUser $member): void;

    public function remove(OrganizationUser $member): void;
}
