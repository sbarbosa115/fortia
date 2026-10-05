<?php

namespace App\Shared\Infrastructure\Analytics;

use App\Shared\Application\Analytics\AnalyticsEndpoints;
use App\Shared\Domain\Event\DomainEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends every domain event to the usage/analytics service (PRD §13.8 POST /events) of its account, or the platform's,
 * on the worker. Nothing is sent when no service is set. A failure is logged and never retried or thrown: analytics
 * must not block anything (A5). A server the account chose may not resolve to a private address unless
 * ANALYTICS_ALLOW_PRIVATE_HOSTS is on (dev).
 */
#[AsMessageHandler(bus: 'event.bus', handles: DomainEvent::class)]
final class ForwardDomainEvent
{
    private const TIMEOUT_SECONDS = 5;

    public function __construct(
        private readonly AnalyticsEndpoints $endpoints,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
        #[Autowire(env: 'APP_ENV')]
        private readonly string $source,
        #[Autowire(env: 'bool:ANALYTICS_ALLOW_PRIVATE_HOSTS')]
        private readonly bool $allowPrivateHosts,
    ) {
    }

    public function __invoke(DomainEvent $event): void
    {
        try {
            $endpoint = $this->endpoints->endpointFor($event->customerId());
            if (null === $endpoint) {
                return;
            }
            $http = $endpoint->ownedByAccount && !$this->allowPrivateHosts ? new NoPrivateNetworkHttpClient($this->http) : $this->http;
            $headers = ['Accept' => 'application/json'];
            if ('' !== $endpoint->apiKey) {
                $headers['X-Api-Key'] = $endpoint->apiKey;
                $headers['Authorization'] = 'Bearer '.$endpoint->apiKey;
            }
            $occurredAt = property_exists($event, 'occurredAt') && \is_string($event->occurredAt) ? $event->occurredAt : (new \DateTimeImmutable())->format(\DATE_ATOM);
            $status = $http->request('POST', $endpoint->eventsUrl(), [
                'headers' => $headers,
                'json' => [
                    'event_type' => $event->eventType(),
                    'customer_id' => $event->customerId(),
                    'payload' => $event->payload(),
                    'occurred_at' => $occurredAt,
                    'source' => $this->source,
                ],
                'timeout' => self::TIMEOUT_SECONDS,
                'max_redirects' => 0,
            ])->getStatusCode();
            if ($status >= 300) {
                $this->logger->warning('Analytics event not accepted', ['event' => $event->eventType(), 'customer' => $event->customerId(), 'status' => $status]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Analytics event not sent', ['event' => $event->eventType(), 'customer' => $event->customerId(), 'error' => $e->getMessage()]);
        }
    }
}
