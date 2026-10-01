<?php

namespace App\Integrations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** A call to the external API (PRD §8.11, §12). Counts one "api" (§7.2). Payload: {api_key_id, endpoint}. */
final class ApiUsage extends BaseDomainEvent
{
    public static function of(string $customerId, string $apiKeyId, string $endpoint): self
    {
        return new self($customerId, 'api', ['api_key_id' => $apiKeyId, 'endpoint' => $endpoint]);
    }
}
