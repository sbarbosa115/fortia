<?php

namespace App\Integrations\Application;

use App\Integrations\Application\Port\WebhookSigningSecret;
use App\Integrations\Application\Port\WebhookTransport;
use App\Integrations\Domain\Event\WebhookDelivered;
use App\Integrations\Domain\Model\WebhookDelivery;
use App\Integrations\Domain\Model\WebhookSubscription;
use App\Integrations\Domain\WebhookRetryPolicy;
use App\Integrations\Domain\WebhookSignature;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;

/**
 * One attempt at a delivery (PRD §7.14, D19): POST the signed body with X-Signature and X-Event-Type, log the
 * outcome on the delivery, schedule the next retry on failure, and count one "webhook" on success.
 */
final class WebhookDeliverer
{
    public function __construct(
        private readonly WebhookTransport $transport,
        private readonly EventBus $events,
        private readonly Clock $clock,
        private readonly WebhookSigningSecret $secret,
    ) {
    }

    public function attempt(WebhookDelivery $delivery, WebhookSubscription $webhook): void
    {
        $body = WebhookPayload::body($delivery->payload());
        $response = $this->transport->post($webhook->url(), $body, [
            'Content-Type' => 'application/json',
            'X-Signature' => WebhookSignature::of($body, $this->secret->value()),
            'X-Event-Type' => $delivery->eventType(),
            'X-Delivery-Id' => $delivery->id(),
        ]);
        $now = $this->clock->now();
        if ($response->isSuccess()) {
            $delivery->recordSuccess((int) $response->statusCode, $now);
            $this->events->publish(WebhookDelivered::of($delivery->customerId(), $delivery->id(), $delivery->webhookId(), $delivery->eventType(), $delivery->attempts()));

            return;
        }
        $delivery->recordFailure($response->statusCode, $response->describe(), WebhookRetryPolicy::nextAttemptAt($delivery->attempts() + 1, $now), $now);
    }
}
