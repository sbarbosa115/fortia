<?php

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Persistence\Model\DomainEventRecord;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Every domain event goes into the analytics event log (PRD §12, §13.8). */
#[AsMessageHandler(bus: 'event.bus', handles: DomainEvent::class)]
final class RecordDomainEvent
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function __invoke(DomainEvent $event): void
    {
        $occurredAt = property_exists($event, 'occurredAt') && \is_string($event->occurredAt)
            ? new \DateTimeImmutable($event->occurredAt)
            : new \DateTimeImmutable();

        $this->em->persist(new DomainEventRecord(
            $event->eventType(),
            $event->customerId(),
            $event->feature(),
            $event->payload(),
            $occurredAt,
        ));
    }
}
