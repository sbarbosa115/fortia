<?php

declare(strict_types=1);

namespace App\Shared\Application\Security;

/**
 * Who the request runs as (PRD §4). Controllers receive it as a `Caller $caller` argument, which also requires a
 * signed-in console user (401 otherwise).
 *
 * While an Admin assumes a customer (X-Assume-Customer-Id, §4.4) the Caller is that account's root user: groups
 * [Customer-Admin], root = true, no admin bypass, and that customer's plan limits. $realEmail keeps who is really
 * behind the request, for the impersonation log.
 */
final class Caller
{
    public const ADMIN = 'Admin';
    public const CUSTOMER_ADMIN = 'Customer-Admin';
    public const CUSTOMER_READ_ONLY = 'Customer-Read-Only';
    /** The groups that may create users and use "admin" write endpoints (§4.2). */
    public const ADMIN_GROUPS = [self::ADMIN, self::CUSTOMER_ADMIN];
    public const ASSIGNABLE_ROLES = [self::CUSTOMER_ADMIN, self::CUSTOMER_READ_ONLY];

    /**
     * @param list<string> $groups
     */
    public function __construct(
        public readonly string $userId,
        public readonly string $email,
        public readonly string $name,
        public readonly string $customerId,
        public readonly array $groups,
        public readonly bool $root,
        public readonly ?string $realEmail = null,
    ) {
    }

    /** A super-admin (not while assuming a customer). */
    public function isAdmin(): bool
    {
        return \in_array(self::ADMIN, $this->groups, true);
    }

    /** In ADMIN_GROUPS: may create users and use the "AG" endpoints of PRD §8. */
    public function inAdminGroups(): bool
    {
        return [] !== array_intersect(self::ADMIN_GROUPS, $this->groups);
    }

    /** Write permission in the console = root or Admin or Customer-Admin, from the full list of groups (§4.2). */
    public function canWrite(): bool
    {
        return $this->root || $this->inAdminGroups();
    }

    /** The displayed role: Admin > Customer-Admin > Customer-Read-Only. */
    public function displayedRole(): string
    {
        foreach ([self::ADMIN, self::CUSTOMER_ADMIN, self::CUSTOMER_READ_ONLY] as $role) {
            if (\in_array($role, $this->groups, true)) {
                return $role;
            }
        }

        return self::CUSTOMER_READ_ONLY;
    }

    /** Whether the caller may see a record of $customerId (their own account, or any as Admin). */
    public function owns(string $customerId): bool
    {
        return $this->isAdmin() || $customerId === $this->customerId;
    }

    public function isAssuming(): bool
    {
        return null !== $this->realEmail;
    }
}
