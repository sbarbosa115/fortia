<?php

declare(strict_types=1);

namespace App\Commerce\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A product of an account's catalog (PRD §6.11): scraped from a store's website or synced from the e-commerce
 * platform. The description is HTML and is sanitized before it is shown (D11). The price is a decimal parsed
 * leniently from text.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\Index(name: 'idx_product_customer', columns: ['customer_id'])]
#[ORM\Index(name: 'idx_product_source', columns: ['source_url'])]
#[ORM\Index(name: 'idx_product_questionnaire', columns: ['questionnaire_id'])]
class Product
{
    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $price = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $productUrl = null;

    #[ORM\Column(length: 768, nullable: true)]
    private ?string $sourceUrl = null;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $questionnaireId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 36)]
        private string $productId,
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 500)]
        private string $name,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->updatedAt = $createdAt;
    }

    public function describe(string $name, string $description, ?string $price, ?string $imageUrl, ?string $productUrl, \DateTimeImmutable $at): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price;
        $this->imageUrl = $imageUrl;
        $this->productUrl = $productUrl;
        $this->updatedAt = $at;
    }

    public function placeIn(?string $sourceUrl, ?string $questionnaireId): void
    {
        $this->sourceUrl = $sourceUrl;
        $this->questionnaireId = $questionnaireId;
    }

    /** Parses a price leniently: "$1,299.90" → "1299.90"; "1.299,90 €" → "1299.90"; nothing numeric → null. */
    public static function parsePrice(mixed $value): ?string
    {
        if (\is_int($value) || \is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }
        if (!\is_string($value)) {
            return null;
        }
        $digits = (string) preg_replace('/[^\d.,]/', '', $value);
        if ('' === $digits) {
            return null;
        }
        $lastComma = strrpos($digits, ',');
        $lastDot = strrpos($digits, '.');
        if (false !== $lastComma && (false === $lastDot || $lastComma > $lastDot) && \strlen($digits) - $lastComma - 1 <= 2) {
            $digits = str_replace(['.', ','], ['', '.'], $digits);
        } else {
            $digits = str_replace(',', '', $digits);
        }
        if (!is_numeric($digits)) {
            return null;
        }

        return number_format((float) $digits, 2, '.', '');
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function price(): ?string
    {
        return $this->price;
    }

    public function imageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function productUrl(): ?string
    {
        return $this->productUrl;
    }

    public function sourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function questionnaireId(): ?string
    {
        return $this->questionnaireId;
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
