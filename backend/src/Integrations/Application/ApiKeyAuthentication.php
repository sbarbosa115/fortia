<?php

namespace App\Integrations\Application;

use App\Integrations\Domain\ApiKeyFormat;
use App\Integrations\Domain\Error\InvalidApiKey;
use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use App\Shared\Domain\Clock;

/** Who an X-API-Key belongs to (PRD §8.11): one 401 INVALID_API_KEY whether it is missing, unknown, revoked or expired. */
final class ApiKeyAuthentication
{
    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array{api_key_id: string, customer_id: string}
     *
     * @throws InvalidApiKey
     */
    public function authenticate(?string $key): array
    {
        if (null === $key || !ApiKeyFormat::isWellFormed($key)) {
            throw new InvalidApiKey();
        }
        $apiKey = $this->keys->find(ApiKey::hash($key));
        if (null === $apiKey || !$apiKey->isUsableAt($this->clock->now())) {
            throw new InvalidApiKey();
        }

        return ['api_key_id' => $apiKey->id(), 'customer_id' => $apiKey->customerId()];
    }
}
