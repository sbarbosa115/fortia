<?php

namespace App\Tests\Unit\Shared\Analytics;

use App\Shared\Application\Analytics\AnalyticsEndpoint;
use PHPUnit\Framework\TestCase;

final class AnalyticsEndpointTest extends TestCase
{
    public function testTheAccountsOwnServiceWinsWithItsOwnKey(): void
    {
        $endpoint = AnalyticsEndpoint::resolve('https://stats.acme.test/', 'acme-key', 'https://usage.mappi.test', 'platform-key');

        self::assertSame('https://stats.acme.test/events', $endpoint?->eventsUrl(), 'the account\'s base URL first (/profile › System)');
        self::assertSame('acme-key', $endpoint->apiKey);
        self::assertTrue($endpoint->ownedByAccount);
    }

    public function testThePlatformKeyIsNeverSentToAServerTheAccountChose(): void
    {
        $endpoint = AnalyticsEndpoint::resolve('https://stats.acme.test', null, 'https://usage.mappi.test', 'platform-key');

        self::assertSame('https://stats.acme.test', $endpoint?->baseUrl);
        self::assertSame('', $endpoint->apiKey, 'an account URL without a key is called without one, never with the platform key');
    }

    public function testWithoutAnAccountServiceTheEnvServiceIsUsed(): void
    {
        $endpoint = AnalyticsEndpoint::resolve(null, 'acme-key', 'https://usage.mappi.test/', 'platform-key');

        self::assertSame('https://usage.mappi.test/events', $endpoint?->eventsUrl(), 'fallback: ANALYTICS_BASE_URL');
        self::assertSame('platform-key', $endpoint->apiKey, 'with ANALYTICS_API_KEY; an account key without a URL is ignored');
        self::assertFalse($endpoint->ownedByAccount);
    }

    public function testWithoutAnyBaseUrlNothingIsSent(): void
    {
        self::assertNull(AnalyticsEndpoint::resolve('', 'acme-key', ' ', 'platform-key'), 'no service set: events are not forwarded');
    }
}
