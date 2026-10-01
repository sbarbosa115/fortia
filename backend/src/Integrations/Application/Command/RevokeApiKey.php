<?php

namespace App\Integrations\Application\Command;

use App\Shared\Application\Security\Caller;

/** DELETE /api-keys/{id} (PRD §8.11): revokes (the row stays, status = revoked). 404 API_KEY_NOT_FOUND otherwise. */
final class RevokeApiKey
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $apiKeyId,
    ) {
    }
}
