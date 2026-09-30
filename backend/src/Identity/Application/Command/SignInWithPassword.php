<?php

declare(strict_types=1);

namespace App\Identity\Application\Command;

/** Email + password sign-in (the identity provider's job, PRD §13.1). Returns a TokenPair. */
final class SignInWithPassword
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}
