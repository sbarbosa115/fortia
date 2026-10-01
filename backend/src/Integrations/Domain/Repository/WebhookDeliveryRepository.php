<?php

namespace App\Integrations\Domain\Repository;

use App\Integrations\Domain\Model\WebhookDelivery;

interface WebhookDeliveryRepository
{
    public function find(string $id): ?WebhookDelivery;

    public function add(WebhookDelivery $delivery): void;

    /** @return list<WebhookDelivery> the latest deliveries of a webhook, newest first */
    public function latestOf(string $webhookId, int $limit): array;

    /** @return list<string> ids of pending deliveries whose next attempt is due, oldest first */
    public function dueIds(\DateTimeImmutable $now, int $limit): array;

    /** Deletes the delivery log of a webhook (when the webhook is deleted). */
    public function removeByWebhook(string $webhookId): void;
}
