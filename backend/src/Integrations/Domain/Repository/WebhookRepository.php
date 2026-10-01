<?php

namespace App\Integrations\Domain\Repository;

use App\Integrations\Domain\Model\WebhookSubscription;

interface WebhookRepository
{
    public function find(string $id): ?WebhookSubscription;

    /** @return list<WebhookSubscription> the account's webhooks, oldest first */
    public function listFor(string $customerId): array;

    /** @return list<WebhookSubscription> the account's receivers of one event type */
    public function subscribedTo(string $customerId, string $eventType): array;

    public function add(WebhookSubscription $webhook): void;

    public function remove(WebhookSubscription $webhook): void;
}
