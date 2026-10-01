<?php

namespace App\Integrations\Infrastructure\Persistence;

use App\Integrations\Domain\Model\WebhookSubscription;
use App\Integrations\Domain\Repository\WebhookRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<WebhookSubscription> */
final class DoctrineWebhookRepository extends DoctrineRepository implements WebhookRepository
{
    protected function entityClass(): string
    {
        return WebhookSubscription::class;
    }

    public function find(string $id): ?WebhookSubscription
    {
        return $this->findEntity($id);
    }

    public function listFor(string $customerId): array
    {
        return $this->repository()->findBy(['customerId' => $customerId], ['createdAt' => 'ASC', 'id' => 'ASC']);
    }

    public function subscribedTo(string $customerId, string $eventType): array
    {
        return $this->repository()->findBy(['customerId' => $customerId, 'eventType' => $eventType], ['createdAt' => 'ASC']);
    }

    public function add(WebhookSubscription $webhook): void
    {
        $this->persist($webhook);
    }

    public function remove(WebhookSubscription $webhook): void
    {
        $this->delete($webhook);
    }
}
