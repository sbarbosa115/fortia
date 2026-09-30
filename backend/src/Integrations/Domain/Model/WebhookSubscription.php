<?php

namespace App\Integrations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A customer's webhook receiver (PRD §6.21): https only, event questionnaire.completed, method POST. */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_subscription')]
#[ORM\Index(name: 'idx_webhook_customer', columns: ['customer_id', 'event_type'])]
class WebhookSubscription
{
    public const QUESTIONNAIRE_COMPLETED = 'questionnaire.completed';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 2048)]
        private string $url,
        #[ORM\Column(length: 64)]
        private string $eventType,
        #[ORM\Column(length: 10)]
        private string $method,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function change(string $url, string $eventType, string $method, \DateTimeImmutable $at): void
    {
        $this->url = $url;
        $this->eventType = $eventType;
        $this->method = $method;
        $this->updatedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function url(): string
    {
        return $this->url;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
