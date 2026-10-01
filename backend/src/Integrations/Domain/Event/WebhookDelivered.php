<?php

namespace App\Integrations\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/**
 * A webhook delivery the receiver accepted (2xx). Counts one "webhook" (PRD §7.2 "each successful webhook
 * delivery"). Payload: {delivery_id, webhook_id, event_type, attempts}.
 */
final class WebhookDelivered extends BaseDomainEvent
{
    public static function of(string $customerId, string $deliveryId, string $webhookId, string $eventType, int $attempts): self
    {
        return new self($customerId, 'webhook', [
            'delivery_id' => $deliveryId,
            'webhook_id' => $webhookId,
            'event_type' => $eventType,
            'attempts' => $attempts,
        ]);
    }
}
