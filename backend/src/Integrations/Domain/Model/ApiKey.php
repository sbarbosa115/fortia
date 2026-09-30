<?php

namespace App\Integrations\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An external API key (PRD §6.20). Its id is the SHA-256 of the key; the plaintext ("QAIRE-" + 64 hex) is shown once
 * and never stored. Revoking keeps the row (status = revoked).
 */
#[ORM\Entity]
#[ORM\Table(name: 'api_key')]
#[ORM\Index(name: 'idx_api_key_customer', columns: ['customer_id', 'status', 'created_at'])]
class ApiKey
{
    public const ACTIVE = 'active';
    public const REVOKED = 'revoked';

    #[ORM\Column(length: 10)]
    private string $status = self::ACTIVE;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $id,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 100)]
        private string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $expiresAt = null,
    ) {
    }

    public static function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public function revoke(): void
    {
        $this->status = self::REVOKED;
    }

    public function recordUse(\DateTimeImmutable $at): void
    {
        $this->lastUsedAt = $at;
    }

    /** Active and not expired: the only kind that authenticates (PRD §8.11). */
    public function isUsableAt(\DateTimeImmutable $now): bool
    {
        return self::ACTIVE === $this->status && (null === $this->expiresAt || $this->expiresAt > $now);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function expiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function lastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }
}
