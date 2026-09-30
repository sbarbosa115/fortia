<?php

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** A refresh token (30 days, PRD §13.1), stored as its SHA-256 hash only. */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_token')]
#[ORM\Index(name: 'idx_refresh_user', columns: ['user_id'])]
class RefreshToken
{
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 64)]
        private string $tokenHash,
        #[ORM\Column(length: 36)]
        private string $userId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $expiresAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
    }

    public function tokenHash(): string
    {
        return $this->tokenHash;
    }

    public function userId(): string
    {
        return $this->userId;
    }

    public function isExpiredAt(\DateTimeImmutable $now): bool
    {
        return $this->expiresAt <= $now;
    }

    public function expiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
