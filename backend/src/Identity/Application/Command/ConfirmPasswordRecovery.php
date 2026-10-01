<?php

namespace App\Identity\Application\Command;

/**
 * Sets a new password with the emailed code (PRD §8.2 POST /password-recovery/confirm). Returns one of the
 * PasswordRecovery outcomes; the command commits even when the code is wrong, so the attempt is counted.
 */
final class ConfirmPasswordRecovery
{
    public function __construct(
        public readonly string $email,
        public readonly string $code,
        #[\SensitiveParameter]
        public readonly string $password,
    ) {
    }
}
