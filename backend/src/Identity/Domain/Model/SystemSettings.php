<?php

namespace App\Identity\Domain\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An account's system settings (the System tab of /profile): its own SMTP server, its own OpenAI API key and its own
 * usage/analytics service (PRD §13.8: base URL + API key). Kept apart from Customer::$settings, which the respondent
 * app reads publicly. Secrets are stored sealed (SecretBox); without them the platform's MAILER_DSN, OPENAI_API_KEY
 * and ANALYTICS_BASE_URL/ANALYTICS_API_KEY are used.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_system_settings')]
class SystemSettings
{
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpHost = null;

    #[ORM\Column(nullable: true)]
    private ?int $smtpPort = null;

    #[ORM\Column(length: 8, nullable: true)]
    private ?string $smtpEncryption = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $smtpUsername = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $smtpPassword = null;

    #[ORM\Column(length: 254, nullable: true)]
    private ?string $smtpFromEmail = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $smtpFromName = null;

    #[ORM\Column(name: 'openai_api_key', type: Types::TEXT, nullable: true)]
    private ?string $openAiApiKey = null;

    #[ORM\Column(name: 'openai_api_key_last4', length: 4, nullable: true)]
    private ?string $openAiApiKeyHint = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $analyticsBaseUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $analyticsApiKey = null;

    #[ORM\Column(name: 'analytics_api_key_last4', length: 4, nullable: true)]
    private ?string $analyticsApiKeyHint = null;

    public function __construct(
        #[ORM\Id]
        #[ORM\Column(length: 16)]
        private string $customerId,
        #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function smtp(): ?SmtpConfiguration
    {
        if (null === $this->smtpHost || null === $this->smtpPort || null === $this->smtpEncryption || null === $this->smtpFromEmail) {
            return null;
        }

        return new SmtpConfiguration($this->smtpHost, $this->smtpPort, $this->smtpEncryption, $this->smtpUsername, $this->smtpPassword, $this->smtpFromEmail, $this->smtpFromName);
    }

    /** null removes the account's server: its emails go back to the platform server. */
    public function changeSmtp(?SmtpConfiguration $smtp, \DateTimeImmutable $now): void
    {
        $this->smtpHost = $smtp?->host;
        $this->smtpPort = $smtp?->port;
        $this->smtpEncryption = $smtp?->encryption;
        $this->smtpUsername = $smtp?->username;
        $this->smtpPassword = $smtp?->sealedPassword;
        $this->smtpFromEmail = $smtp?->fromEmail;
        $this->smtpFromName = $smtp?->fromName;
        $this->updatedAt = $now;
    }

    public function sealedOpenAiKey(): ?string
    {
        return $this->openAiApiKey;
    }

    /** The last 4 characters of the key, the only part the console shows. */
    public function openAiKeyHint(): ?string
    {
        return $this->openAiApiKeyHint;
    }

    /**
     * @param string|null $sealed the key sealed by SecretBox; null removes it (the platform key is used)
     * @param string|null $clear  the clear key, only to keep its last 4 characters
     */
    public function changeOpenAiKey(?string $sealed, #[\SensitiveParameter] ?string $clear, \DateTimeImmutable $now): void
    {
        $this->openAiApiKey = $sealed;
        $this->openAiApiKeyHint = null === $sealed || null === $clear ? null : substr($clear, -4);
        $this->updatedAt = $now;
    }

    public function analyticsBaseUrl(): ?string
    {
        return $this->analyticsBaseUrl;
    }

    /** null or "" removes the account's service: its events go to the platform's ANALYTICS_BASE_URL. */
    public function changeAnalyticsBaseUrl(?string $baseUrl, \DateTimeImmutable $now): void
    {
        $baseUrl = null === $baseUrl ? '' : rtrim(trim($baseUrl), '/');
        $this->analyticsBaseUrl = '' === $baseUrl ? null : $baseUrl;
        $this->updatedAt = $now;
    }

    public function sealedAnalyticsKey(): ?string
    {
        return $this->analyticsApiKey;
    }

    /** The last 4 characters of the analytics key, the only part the console shows. */
    public function analyticsKeyHint(): ?string
    {
        return $this->analyticsApiKeyHint;
    }

    /**
     * @param string|null $sealed the key sealed by SecretBox; null removes it
     * @param string|null $clear  the clear key, only to keep its last 4 characters
     */
    public function changeAnalyticsKey(?string $sealed, #[\SensitiveParameter] ?string $clear, \DateTimeImmutable $now): void
    {
        $this->analyticsApiKey = $sealed;
        $this->analyticsApiKeyHint = null === $sealed || null === $clear ? null : substr($clear, -4);
        $this->updatedAt = $now;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
