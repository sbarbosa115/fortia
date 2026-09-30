<?php

namespace App\Organizations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A group of people belonging to a customer, to which questionnaires are assigned (PRD §6.12). domain_email is
 * unique across the whole system, lowercase.
 */
#[ORM\Entity]
#[ORM\Table(name: 'organization')]
#[ORM\UniqueConstraint(name: 'uniq_organization_domain', columns: ['domain_email'])]
#[ORM\Index(name: 'idx_organization_customer', columns: ['customer_id', 'created_at'])]
class Organization
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domainEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $organizationId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function describe(string $name, ?string $domainEmail, ?string $description, bool $active, \DateTimeImmutable $at): void
    {
        $this->name = $name;
        $this->domainEmail = null === $domainEmail || '' === $domainEmail ? null : mb_strtolower($domainEmail);
        $this->description = $description;
        $this->active = $active;
        $this->updatedAt = $at;
    }

    public function touch(\DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function domainEmail(): ?string
    {
        return $this->domainEmail;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function isActive(): bool
    {
        return $this->active;
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
