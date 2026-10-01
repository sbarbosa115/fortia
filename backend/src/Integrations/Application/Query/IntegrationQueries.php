<?php

namespace App\Integrations\Application\Query;

use App\Integrations\Domain\Model\ApiKey;
use App\Integrations\Domain\Model\WebhookDelivery;
use App\Integrations\Domain\Model\WebhookSubscription;
use App\Integrations\Domain\Repository\ApiKeyRepository;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Integrations\Domain\Repository\WebhookRepository;
use App\Shared\Domain\Iso;

/**
 * Reads of the Integrations context, in the PRD §8.11 shapes (the console and the chat's tools use them): API keys
 * without their secret, webhooks, and a webhook's delivery log (D19).
 */
final class IntegrationQueries
{
    public const DELIVERY_LOG_SIZE = 20;

    public function __construct(
        private readonly ApiKeyRepository $keys,
        private readonly WebhookRepository $webhooks,
        private readonly WebhookDeliveryRepository $deliveries,
    ) {
    }

    /** @return list<array{id: string, name: string, created_at: string|null, expires_at: string|null, last_used_at: string|null}> active keys, newest first */
    public function apiKeys(string $customerId): array
    {
        return array_map(static fn (ApiKey $key): array => [
            'id' => $key->id(),
            'name' => $key->name(),
            'created_at' => Iso::datetime($key->createdAt()),
            'expires_at' => Iso::datetime($key->expiresAt()),
            'last_used_at' => Iso::datetime($key->lastUsedAt()),
        ], $this->keys->listActive($customerId));
    }

    /** @return list<array<string, mixed>> */
    public function webhooks(string $customerId): array
    {
        return array_map(self::webhookData(...), $this->webhooks->listFor($customerId));
    }

    /** @return array<string, mixed>|null */
    public function webhook(string $webhookId): ?array
    {
        $webhook = $this->webhooks->find($webhookId);

        return null === $webhook ? null : self::webhookData($webhook);
    }

    /** @return list<array<string, mixed>> the latest deliveries of a webhook, newest first */
    public function deliveries(string $webhookId): array
    {
        return array_map(static fn (WebhookDelivery $delivery): array => [
            'id' => $delivery->id(),
            'webhook_id' => $delivery->webhookId(),
            'event_type' => $delivery->eventType(),
            'status' => $delivery->status(),
            'attempts' => $delivery->attempts(),
            'last_status_code' => $delivery->lastStatusCode(),
            'last_error' => $delivery->lastError(),
            'next_attempt_at' => Iso::datetime($delivery->nextAttemptAt()),
            'created_at' => Iso::datetime($delivery->createdAt()),
            'updated_at' => Iso::datetime($delivery->updatedAt()),
        ], $this->deliveries->latestOf($webhookId, self::DELIVERY_LOG_SIZE));
    }

    /** @return array<string, mixed> */
    private static function webhookData(WebhookSubscription $webhook): array
    {
        return [
            'id' => $webhook->id(),
            'customer_id' => $webhook->customerId(),
            'url' => $webhook->url(),
            'event_type' => $webhook->eventType(),
            'method' => $webhook->method(),
            'created_at' => Iso::datetime($webhook->createdAt()),
            'updated_at' => Iso::datetime($webhook->updatedAt()),
        ];
    }
}
