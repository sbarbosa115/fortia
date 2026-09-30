<?php

namespace App\Commerce\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An account's connection with the e-commerce platform (PRD §6.1 shopify_shop / shopify_token /
 * shopify_refresh_token, kept here by Commerce). The tokens are secrets and are never returned.
 */
#[ORM\Entity]
#[ORM\Table(name: 'shopify_connection')]
class ShopifyConnection
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 255)]
        private string $shop,
        #[ORM\Column(type: Types::TEXT)]
        private string $accessToken,
        #[ORM\Column(type: Types::TEXT, nullable: true)]
        private ?string $refreshToken,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
        private ?\DateTimeImmutable $tokenExpiresAt,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function refresh(string $accessToken, ?string $refreshToken, ?\DateTimeImmutable $expiresAt, \DateTimeImmutable $at): void
    {
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->tokenExpiresAt = $expiresAt;
        $this->updatedAt = $at;
    }

    public function reconnect(string $shop, string $accessToken, ?string $refreshToken, ?\DateTimeImmutable $expiresAt, \DateTimeImmutable $at): void
    {
        $this->shop = $shop;
        $this->refresh($accessToken, $refreshToken, $expiresAt, $at);
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function shop(): string
    {
        return $this->shop;
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function tokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
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
