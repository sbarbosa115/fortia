<?php

namespace App\Tests\Unit\Shared\Analytics;

use App\Shared\Application\Analytics\AnalyticsEndpoint;
use App\Shared\Application\Analytics\AnalyticsEndpoints;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Analytics\ForwardDomainEvent;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ForwardDomainEventTest extends TestCase
{
    private function event(): DomainEvent
    {
        return new class implements DomainEvent {
            public string $occurredAt = '2026-10-04T12:00:00+00:00';

            public function eventType(): string
            {
                return 'QuestionnaireCreated';
            }

            public function customerId(): string
            {
                return 'ACME0001';
            }

            public function payload(): array
            {
                return ['questionnaire_id' => 'q-1'];
            }
        };
    }

    private function endpoints(?AnalyticsEndpoint $endpoint): AnalyticsEndpoints
    {
        return new class($endpoint) implements AnalyticsEndpoints {
            public function __construct(private readonly ?AnalyticsEndpoint $endpoint)
            {
            }

            public function endpointFor(?string $customerId): ?AnalyticsEndpoint
            {
                return $this->endpoint;
            }
        };
    }

    public function testTheEventIsPostedToTheServiceWithItsKey(): void
    {
        $requests = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse('{}', ['http_code' => 202]);
        });
        $forward = new ForwardDomainEvent($this->endpoints(new AnalyticsEndpoint('https://usage.mappi.test', 'platform-key', false)), $http, new NullLogger(), 'prod', false);

        $forward($this->event());

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://usage.mappi.test/events', $requests[0]['url'], 'PRD §13.8 POST /events');
        $headers = implode("\n", $requests[0]['options']['headers']);
        self::assertStringContainsString('X-Api-Key: platform-key', $headers);
        self::assertStringContainsString('Authorization: Bearer platform-key', $headers);
        self::assertSame([
            'event_type' => 'QuestionnaireCreated',
            'customer_id' => 'ACME0001',
            'payload' => ['questionnaire_id' => 'q-1'],
            'occurred_at' => '2026-10-04T12:00:00+00:00',
            'source' => 'prod',
        ], json_decode((string) $requests[0]['options']['body'], true), 'staging and production are told apart by source');
    }

    public function testNothingIsSentWithoutAService(): void
    {
        $http = new MockHttpClient(static fn (): MockResponse => throw new \LogicException('no request expected'));
        $forward = new ForwardDomainEvent($this->endpoints(null), $http, new NullLogger(), 'prod', false);

        $forward($this->event());

        self::assertSame(0, $http->getRequestsCount());
    }

    public function testAFailingServiceNeverThrows(): void
    {
        $http = new MockHttpClient([new MockResponse('', ['error' => 'connection refused'])]);
        $forward = new ForwardDomainEvent($this->endpoints(new AnalyticsEndpoint('https://usage.mappi.test', '', false)), $http, new NullLogger(), 'prod', false);

        $forward($this->event());

        self::assertSame(1, $http->getRequestsCount(), 'tried once, the failure only logged (A5: analytics never blocks)');
    }

    public function testAnAccountsServiceOnAPrivateAddressIsRefused(): void
    {
        $http = new MockHttpClient([new MockResponse('{}')]);
        $forward = new ForwardDomainEvent($this->endpoints(new AnalyticsEndpoint('http://127.0.0.1:8080', 'k', true)), $http, new NullLogger(), 'prod', false);

        $forward($this->event());

        self::assertSame(0, $http->getRequestsCount(), 'an account cannot use its analytics URL to reach the internal network');
    }
}
