<?php

namespace App\Shared\Application\Analytics;

/**
 * Where an account's domain events go: the usage/analytics service (PRD §13.8), a base URL and its API key.
 *
 * The account's own service (/profile › System) wins when it saved a base URL, with its own key only (the platform
 * key is never sent to a server an account chose); otherwise ANALYTICS_BASE_URL with ANALYTICS_API_KEY; without
 * either base URL nothing is sent.
 *
 *     AnalyticsEndpoint::resolve('https://stats.acme.test', 'acme-key', 'https://usage.mappi.test', 'platform-key');
 *     // → https://stats.acme.test with acme-key, ownedByAccount: true
 */
final class AnalyticsEndpoint
{
    public function __construct(
        public readonly string $baseUrl,
        #[\SensitiveParameter]
        public readonly string $apiKey,
        /** The account chose the server: it may point anywhere, so private addresses are refused. */
        public readonly bool $ownedByAccount,
    ) {
    }

    public static function resolve(?string $accountUrl, #[\SensitiveParameter] ?string $accountKey, string $platformUrl, #[\SensitiveParameter] string $platformKey): ?self
    {
        $accountUrl = rtrim(trim((string) $accountUrl), '/');
        if ('' !== $accountUrl) {
            return new self($accountUrl, trim((string) $accountKey), true);
        }
        $platformUrl = rtrim(trim($platformUrl), '/');
        if ('' !== $platformUrl) {
            return new self($platformUrl, trim($platformKey), false);
        }

        return null;
    }

    /** POST {base_url}/events (§13.8). */
    public function eventsUrl(): string
    {
        return $this->baseUrl.'/events';
    }
}
