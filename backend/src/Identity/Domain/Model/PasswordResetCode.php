<?php

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A password recovery code sent by email (PRD §8.2), stored hashed, with an expiry and an attempt count. */
#[ORM\Entity]
#[ORM\Table(name: 'password_reset_code')]
#[ORM\Index(name: 'idx_reset_email', columns: ['email', 'created_at'])]
class PasswordResetCode
{
    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $id,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column(length: 64)]
        private string $codeHash,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function codeHash(): string
    {
        return $this->codeHash;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function recordAttempt(): void
    {
        ++$this->attempts;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function markUsed(\DateTimeImmutable $at): void
    {
        $this->usedAt = $at;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
