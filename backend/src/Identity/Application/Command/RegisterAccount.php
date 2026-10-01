<?php

namespace App\Identity\Application\Command;

/**
 * Native sign-up (PRD §8.2 POST /register): a new account with its root user, who then signs in with the password.
 * Returns ['customer_id', 'email', 'name'].
 */
final class RegisterAccount
{
    public function __construct(
        public readonly string $email,
        #[\SensitiveParameter]
        public readonly string $password,
        public readonly string $name,
        public readonly string $language,
        public readonly string $source,
    ) {
    }
}
