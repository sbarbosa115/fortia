<?php

namespace App\Identity\Infrastructure\Secrets;

use App\Identity\Domain\Repository\SystemSettingsRepository;
use App\Shared\Application\Analytics\AnalyticsEndpoint;
use App\Shared\Application\Analytics\AnalyticsEndpoints;
use App\Shared\Application\Llm\OpenAiKeys;
use App\Shared\Application\Mail\CustomerMailServers;
use App\Shared\Application\Mail\SmtpServer;
use App\Shared\Application\Security\SecretBox;
use App\Shared\Application\Security\SecretNotReadable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Opens an account's sealed secrets for the mailer, the language model and the analytics service. A secret that
 * cannot be opened (SETTINGS_ENCRYPTION_KEY changed) is logged and treated as not set, so the platform defaults keep
 * working.
 */
final class SystemSettingsSecrets implements CustomerMailServers, OpenAiKeys, AnalyticsEndpoints
{
    public function __construct(
        private readonly SystemSettingsRepository $settings,
        private readonly SecretBox $box,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'OPENAI_API_KEY')]
        private readonly string $platformOpenAiKey,
        #[Autowire(env: 'ANALYTICS_BASE_URL')]
        private readonly string $platformAnalyticsUrl,
        #[Autowire(env: 'ANALYTICS_API_KEY')]
        private readonly string $platformAnalyticsKey,
    ) {
    }

    public function serverFor(string $customerId): ?SmtpServer
    {
        $smtp = $this->settings->find($customerId)?->smtp();
        if (null === $smtp) {
            return null;
        }
        try {
            $password = null === $smtp->sealedPassword ? null : $this->box->open($smtp->sealedPassword);
        } catch (SecretNotReadable $e) {
            $this->logger->error('The SMTP password of account {customer} cannot be opened: {reason}', ['customer' => $customerId, 'reason' => $e->getMessage()]);

            return null;
        }

        return new SmtpServer($smtp->host, $smtp->port, $smtp->encryption, $smtp->username, $password, $smtp->fromEmail, $smtp->fromName);
    }

    public function keyFor(?string $customerId): string
    {
        $sealed = null === $customerId ? null : $this->settings->find($customerId)?->sealedOpenAiKey();
        if (null !== $sealed) {
            try {
                return $this->box->open($sealed);
            } catch (SecretNotReadable $e) {
                $this->logger->error('The OpenAI key of account {customer} cannot be opened: {reason}', ['customer' => $customerId, 'reason' => $e->getMessage()]);
            }
        }

        return trim($this->platformOpenAiKey);
    }

    public function endpointFor(?string $customerId): ?AnalyticsEndpoint
    {
        $settings = null === $customerId ? null : $this->settings->find($customerId);
        $accountUrl = $settings?->analyticsBaseUrl();
        $accountKey = null;
        $sealed = null === $settings || null === $accountUrl ? null : $settings->sealedAnalyticsKey();
        if (null !== $sealed) {
            try {
                $accountKey = $this->box->open($sealed);
            } catch (SecretNotReadable $e) {
                $this->logger->error('The analytics key of account {customer} cannot be opened: {reason}', ['customer' => $customerId, 'reason' => $e->getMessage()]);
            }
        }

        return AnalyticsEndpoint::resolve($accountUrl, $accountKey, $this->platformAnalyticsUrl, $this->platformAnalyticsKey);
    }
}
