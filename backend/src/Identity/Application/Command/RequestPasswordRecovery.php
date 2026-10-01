<?php

namespace App\Identity\Application\Command;

/**
 * "Forgot my password" (PRD §8.2 POST /password-recovery). For an existing user, a code is emailed after the commit;
 * for anyone else nothing happens, and the caller always answers the same (§14.2).
 */
final class RequestPasswordRecovery
{
    public function __construct(public readonly string $email)
    {
    }
}
