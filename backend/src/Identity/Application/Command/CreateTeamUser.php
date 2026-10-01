<?php

namespace App\Identity\Application\Command;

/**
 * A team user in the caller's account (PRD §8.2 POST /users), with a permanent password and an assignable role.
 * Returns ['email', 'name', 'root', 'role', 'customer_id'].
 */
final class CreateTeamUser
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $email,
        public readonly string $name,
        public readonly string $role,
        #[\SensitiveParameter]
        public readonly string $password,
    ) {
    }
}
