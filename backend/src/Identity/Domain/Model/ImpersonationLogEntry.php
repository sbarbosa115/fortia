<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** D18: who assumed which customer, and what they did while assuming. One row per write request. */
#[ORM\Entity]
#[ORM\Table(name: 'impersonation_log')]
#[ORM\Index(name: 'idx_impersonation_customer', columns: ['customer_id', 'occurred_at'])]
class ImpersonationLogEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?string $id = null;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $adminEmail,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 10)]
        private string $method,
        #[ORM\Column(length: 512)]
        private string $path,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $occurredAt,
    ) {
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function adminEmail(): string
    {
        return $this->adminEmail;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
