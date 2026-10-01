<?php

namespace App\Billing\Infrastructure\Gateway\Fake\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A webhook event the fake gateway has to deliver to POST /api/v1/checkout/webhook (its outbox), in order. Delivered,
 * signed, at the end of the request that caused it (FakeWebhookDelivery).
 */
#[ORM\Entity]
#[ORM\Table(name: 'fake_gateway_event')]
#[ORM\UniqueConstraint(name: 'uniq_fake_gateway_event_id', columns: ['event_id'])]
#[ORM\Index(name: 'idx_fake_gateway_event_pending', columns: ['delivered_at'])]
class FakeGatewayEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $seq = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $responseStatus = null;

    public function __construct(
        #[ORM\Column(length: 64)]
        private string $eventId,
        #[ORM\Column(length: 100)]
        private string $type,
        #[ORM\Column(type: Types::TEXT)]
        private string $payload,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function seq(): ?int
    {
        return $this->seq;
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function deliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function responseStatus(): ?int
    {
        return $this->responseStatus;
    }
}
