<?php

namespace App\Integrations\Application\Command;

use App\Shared\Application\Security\Caller;

/**
 * POST /api-keys (PRD §8.11): a new key for the caller's account, named 1–100, expiring after 1–3650 days or never.
 * The handler returns the plaintext key: the only time it exists outside the client.
 */
final class CreateApiKey
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $name,
        public readonly ?int $expirationDays = null,
    ) {
    }
}
