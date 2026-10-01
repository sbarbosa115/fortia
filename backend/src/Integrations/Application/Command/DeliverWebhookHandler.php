<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Application\WebhookDeliverer;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Integrations\Domain\Repository\WebhookRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeliverWebhookHandler
{
    public function __construct(
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly WebhookRepository $webhooks,
        private readonly WebhookDeliverer $deliverer,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(DeliverWebhook $command): void
    {
        $delivery = $this->deliveries->find($command->deliveryId);
        $now = $this->clock->now();
        if (null === $delivery || !$delivery->isPending() || ($delivery->nextAttemptAt() ?? $now) > $now) {
            return;
        }
        $webhook = $this->webhooks->find($delivery->webhookId());
        if (null === $webhook) {
            $delivery->recordFailure(null, 'Webhook deleted', null, $now);

            return;
        }
        $this->deliverer->attempt($delivery, $webhook);
    }
}
