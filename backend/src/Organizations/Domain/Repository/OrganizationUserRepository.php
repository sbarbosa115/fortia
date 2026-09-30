<?php

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

    /**
     * Makes room for a reconciliation inside the command's transaction, before its flush (PRD §8.7): deletes the
     * members that go away right now, and clears the stored email of the members whose email changes, so the
     * unique (organization, email) index never sees two rows with one email while emails move between members.
     * The members that change get their new email when the command commits.
     *
     * @param list<OrganizationUser> $removed
     * @param list<OrganizationUser> $emailChanged
     */
    public function prepareReconciliation(array $removed, array $emailChanged): void;

    /** Deletes every member of an organization (D2); returns how many. */
    public function removeByOrganization(string $organizationId): int;
}
