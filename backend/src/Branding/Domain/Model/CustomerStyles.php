<?php

namespace App\Branding\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An account's visual branding for the respondent app (PRD §6.18): the website it was read from and the styles
 * (camelCase keys: logoUrl, font, body, h1–h3, p, label, a, button.primary/secondary, input).
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_styles')]
class CustomerStyles
{
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $styles
     */
    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 2048, nullable: true)]
        private ?string $website,
        #[ORM\Column(type: Types::JSON)]
        private array $styles,
        \DateTimeImmutable $at,
    ) {
        $this->updatedAt = $at;
    }

    /** @param array<string, mixed> $styles */
    public function restyle(?string $website, array $styles, \DateTimeImmutable $at): void
    {
        $this->website = $website;
        $this->styles = $styles;
        $this->updatedAt = $at;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function website(): ?string
    {
        return $this->website;
    }

    /** @return array<string, mixed> */
    public function styles(): array
    {
        return $this->styles;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
