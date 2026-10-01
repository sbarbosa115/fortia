<?php

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\ProcessedWebhookEvent;
use App\Billing\Domain\Repository\ProcessedWebhookEventRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<ProcessedWebhookEvent> */
final class DoctrineProcessedWebhookEventRepository extends DoctrineRepository implements ProcessedWebhookEventRepository
{
    protected function entityClass(): string
    {
        return ProcessedWebhookEvent::class;
    }

    public function has(string $eventId): bool
    {
        return null !== $this->findEntity($eventId);
    }

    public function add(ProcessedWebhookEvent $event): void
    {
        $this->persist($event);
    }
}
