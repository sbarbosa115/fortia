<?php

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * The account, the tenant (PRD §6.1). Its users' identity lives in User; its e-commerce connection in Commerce;
 * its brand styles in Branding.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer')]
#[ORM\Index(name: 'idx_customer_source', columns: ['source', 'created_at'])]
class Customer
{
    public const LANGUAGES = ['es-CO', 'en-US'];
    public const DEFAULT_MAX_FILES = 10;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $settings;

    #[ORM\Column(nullable: true)]
    private ?bool $onboardingCompleted;

    /** D15: what onboarding step 2 asks for is saved. */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $workspaceName = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(length: 5)]
        private string $language,
        #[ORM\Column(length: 64)]
        private string $source,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $createdAt,
        ?bool $onboardingCompleted = false,
    ) {
        $this->settings = self::defaultSettings($language);
        $this->onboardingCompleted = $onboardingCompleted;
        $this->updatedAt = $createdAt;
    }

    /** @return array<string, mixed> */
    public static function defaultSettings(string $language): array
    {
        return [
            'language' => $language,
            'transcription_url' => null,
            'pixel_id' => null,
            'linkedin_partner_id' => null,
            'linkedin_conversion_id' => null,
            'google_ads_id' => null,
            'google_ads_conversion_label' => null,
            'max_files' => self::DEFAULT_MAX_FILES,
        ];
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function language(): string
    {
        return $this->language;
    }

    public function source(): string
    {
        return $this->source;
    }

    /** @return array<string, mixed> CustomerSettings (PRD §6.1), with every key present */
    public function settings(): array
    {
        return array_merge(self::defaultSettings($this->language), $this->settings, ['language' => $this->language]);
    }

    /**
     * Applies a settings change (PRD §8.3 PATCH settings): only the keys given change. The language is never null.
     *
     * @param array<string, mixed> $changes
     */
    public function changeSettings(array $changes, \DateTimeImmutable $at): void
    {
        if (isset($changes['language']) && \is_string($changes['language'])) {
            $this->language = $changes['language'];
        }
        unset($changes['language']);
        $this->settings = array_merge($this->settings(), $changes, ['language' => $this->language]);
        $this->updatedAt = $at;
    }

    public function onboardingCompleted(): ?bool
    {
        return $this->onboardingCompleted;
    }

    public function setOnboardingCompleted(bool $completed, \DateTimeImmutable $at): void
    {
        $this->onboardingCompleted = $completed;
        $this->updatedAt = $at;
    }

    public function workspaceName(): ?string
    {
        return $this->workspaceName;
    }

    public function website(): ?string
    {
        return $this->website;
    }

    public function describeWorkspace(?string $name, ?string $website, \DateTimeImmutable $at): void
    {
        $this->workspaceName = $name;
        $this->website = $website;
        $this->updatedAt = $at;
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
