<?php

declare(strict_types=1);

namespace App\Organizations\Application\Query;

use App\Organizations\Domain\Model\Organization;
use App\Organizations\Domain\Model\OrganizationUser;
use App\Organizations\Domain\Repository\OrganizationRepository;
use App\Organizations\Domain\Repository\OrganizationUserRepository;
use App\Shared\Domain\Iso;

/**
 * Reads of the Organizations context for other contexts: an organization with its members, in the PRD §8.7 shape
 * ({organization_id, customer_id, name, domain_email, description, active, created_at, updated_at,
 * organization_users: [{organization_user_id, organization_id, name, email, phone, role, area, …}]}).
 */
final class OrganizationQueries
{
    public function __construct(
        private readonly OrganizationRepository $organizations,
        private readonly OrganizationUserRepository $members,
    ) {
    }

    /** @return array<string, mixed>|null */
    public function find(string $organizationId): ?array
    {
        $organization = $this->organizations->find($organizationId);

        return null === $organization ? null : self::organizationData($organization, $this->members->listByOrganization($organizationId));
    }

    /** @return array<string, mixed>|null */
    public function member(string $organizationUserId): ?array
    {
        $member = $this->members->find($organizationUserId);

        return null === $member ? null : self::memberData($member);
    }

    /** @return list<array<string, mixed>> */
    public function membersOf(string $organizationId): array
    {
        return array_map(self::memberData(...), $this->members->listByOrganization($organizationId));
    }

    /**
     * @param list<OrganizationUser> $members
     *
     * @return array<string, mixed>
     */
    public static function organizationData(Organization $organization, array $members): array
    {
        return [
            'organization_id' => $organization->organizationId(),
            'customer_id' => $organization->customerId(),
            'name' => $organization->name(),
            'domain_email' => $organization->domainEmail(),
            'description' => $organization->description(),
            'active' => $organization->isActive(),
            'created_at' => Iso::datetime($organization->createdAt()),
            'updated_at' => Iso::datetime($organization->updatedAt()),
            'organization_users' => array_map(self::memberData(...), $members),
        ];
    }

    /** @return array<string, mixed> */
    public static function memberData(OrganizationUser $member): array
    {
        return [
            'organization_user_id' => $member->organizationUserId(),
            'organization_id' => $member->organizationId(),
            'name' => $member->name(),
            'email' => $member->email(),
            'phone' => $member->phone(),
            'role' => $member->role(),
            'area' => $member->area(),
            'created_at' => Iso::datetime($member->createdAt()),
            'updated_at' => Iso::datetime($member->updatedAt()),
        ];
    }
}
