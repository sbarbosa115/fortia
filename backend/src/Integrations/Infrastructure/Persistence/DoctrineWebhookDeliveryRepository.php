<?php

namespace App\Integrations\Infrastructure\Persistence;

use App\Integrations\Domain\Model\WebhookDelivery;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<WebhookDelivery> */
final class DoctrineWebhookDeliveryRepository extends DoctrineRepository implements WebhookDeliveryRepository
{
    protected function entityClass(): string
    {
        return WebhookDelivery::class;
    }

    public function find(string $id): ?WebhookDelivery
    {
        return $this->findEntity($id);
    }

    public function add(WebhookDelivery $delivery): void
    {
        $this->persist($delivery);
    }

    public function latestOf(string $webhookId, int $limit): array
    {
        return $this->repository()->findBy(['webhookId' => $webhookId], ['createdAt' => 'DESC', 'id' => 'ASC'], $limit);
    }

    public function dueIds(\DateTimeImmutable $now, int $limit): array
    {
        /** @var list<string> $ids */
        $ids = $this->em->createQueryBuilder()
            ->select('d.id')
            ->from(WebhookDelivery::class, 'd')
            ->where('d.status = :pending')
            ->andWhere('d.nextAttemptAt <= :now')
            ->setParameter('pending', WebhookDelivery::PENDING)
            ->setParameter('now', $now)
            ->orderBy('d.nextAttemptAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getSingleColumnResult();

        return $ids;
    }

    public function removeByWebhook(string $webhookId): void
    {
        $this->em->createQueryBuilder()
            ->delete(WebhookDelivery::class, 'd')
            ->where('d.webhookId = :webhook')
            ->setParameter('webhook', $webhookId)
            ->getQuery()
            ->execute();
    }
}
