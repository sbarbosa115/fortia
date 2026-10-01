<?php

namespace App\Billing\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A payment webhook event already applied (PRD §7.4 "all idempotent"): a redelivery of the same event id is answered
 * 200 without applying it again. Written in the same transaction as its effects, so a failed event is retried.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_processed_webhook_event')]
class ProcessedWebhookEvent
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 255)]
        private string $eventId,
        #[ORM\Column(length: 100)]
        private string $type,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $processedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function processedAt(): \DateTimeImmutable
    {
        return $this->processedAt;
    }
}
