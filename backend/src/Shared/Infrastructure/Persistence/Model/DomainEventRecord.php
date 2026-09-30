<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The analytics event log: every domain event (PRD §12), as the usage/analytics service received them (§13.8,
 * absorbed into the API). Append-only.
 */
#[ORM\Entity]
#[ORM\Table(name: 'domain_event_log')]
#[ORM\Index(name: 'idx_event_customer_type', columns: ['customer_id', 'event_type', 'occurred_at'])]
class DomainEventRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\Column(length: 64)]
        private string $eventType,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $customerId,
        #[ORM\Column(length: 64, nullable: true)]
        private ?string $feature,
        #[ORM\Column(type: Types::JSON)]
        private array $payload,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function feature(): ?string
    {
        return $this->feature;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
