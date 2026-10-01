<?php

namespace App\Billing\Infrastructure\Gateway\Fake;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The fake gateway's webhook sender: at the end of each request (after its command committed, before the response
 * leaves), every event the fake queued is signed and handed to POST /api/v1/checkout/webhook as a sub-request — the
 * same handler, signature check and idempotency a real gateway's delivery goes through. So a paid checkout or an
 * upgrade has been applied by the time the browser (or a test) reads the plan again.
 */
final class FakeWebhookDelivery
{
    private bool $delivering = false;

    public function __construct(
        private readonly FakePaymentGateway $gateway,
        private readonly HttpKernelInterface $kernel,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -1024)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || $this->delivering || !$this->gateway->enabled()) {
            return;
        }
        $this->deliver();
    }

    /** @return int how many events were delivered */
    public function deliver(): int
    {
        $this->delivering = true;
        $delivered = 0;
        try {
            $path = $this->urls->generate('api_checkout_webhook');
            foreach ($this->gateway->pendingEvents() as $pending) {
                $request = Request::create($path, 'POST', [], [], [], [
                    'CONTENT_TYPE' => 'application/json',
                    'HTTP_STRIPE_SIGNATURE' => $this->gateway->sign($pending['payload']),
                ], $pending['payload']);
                $status = $this->kernel->handle($request, HttpKernelInterface::SUB_REQUEST)->getStatusCode();
                // Delivered once: a 500 is logged, not retried forever on every later request.
                $this->gateway->markDelivered($pending['seq'], $status);
                ++$delivered;
                if (200 !== $status) {
                    $this->logger->warning('Fake gateway webhook {event} answered {status}', ['event' => $pending['event_id'], 'status' => $status]);
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error('Fake gateway webhook delivery failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        } finally {
            $this->delivering = false;
        }

        return $delivered;
    }
}
