<?php

namespace App\Integrations\Domain\Repository;

use App\Integrations\Domain\Model\ApiKey;

interface ApiKeyRepository
{
    /** By id (the SHA-256 of the key). */
    public function find(string $id): ?ApiKey;

    /** @return list<ApiKey> the account's active (not revoked) keys, newest first */
    public function listActive(string $customerId): array;

    public function add(ApiKey $key): void;
}
