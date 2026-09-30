<?php

declare(strict_types=1);

namespace App\Integrations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One delivery of an event to one subscription (D19: a delivery log, with retries and backoff).
 * Status: pending → delivered | failed (after the last retry).
 */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_delivery')]
#[ORM\Index(name: 'idx_delivery_webhook', columns: ['webhook_id', 'created_at'])]
#[ORM\Index(name: 'idx_delivery_due', columns: ['status', 'next_attempt_at'])]
class WebhookDelivery
{
    public const PENDING = 'pending';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';

    #[ORM\Column(length: 10)]
    private string $status = self::PENDING;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(nullable: true)]
    private ?int $lastStatusCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextAttemptAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 36)]
        private string $webhookId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 64)]
        private string $eventType,
        #[ORM\Column(type: Types::JSON)]
        private array $payload,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
        $this->nextAttemptAt = $createdAt;
    }

    public function recordSuccess(int $statusCode, \DateTimeImmutable $at): void
    {
        ++$this->attempts;
        $this->status = self::DELIVERED;
        $this->lastStatusCode = $statusCode;
        $this->nextAttemptAt = null;
        $this->updatedAt = $at;
    }

    public function recordFailure(?int $statusCode, ?\DateTimeImmutable $retryAt, \DateTimeImmutable $at): void
    {
        ++$this->attempts;
        $this->lastStatusCode = $statusCode;
        $this->nextAttemptAt = $retryAt;
        $this->status = null === $retryAt ? self::FAILED : self::PENDING;
        $this->updatedAt = $at;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function webhookId(): string
    {
        return $this->webhookId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function eventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function lastStatusCode(): ?int
    {
        return $this->lastStatusCode;
    }

    public function nextAttemptAt(): ?\DateTimeImmutable
    {
        return $this->nextAttemptAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
