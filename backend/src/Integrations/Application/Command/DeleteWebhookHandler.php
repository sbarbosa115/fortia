<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Domain\Error\WebhookNotFound;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Integrations\Domain\Repository\WebhookRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteWebhookHandler
{
    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly WebhookDeliveryRepository $deliveries,
    ) {
    }

    public function __invoke(DeleteWebhook $command): void
    {
        $webhook = $this->webhooks->find($command->webhookId);
        if (null === $webhook || !$command->caller->owns($webhook->customerId())) {
            throw new WebhookNotFound();
        }
        $this->deliveries->removeByWebhook($webhook->id());
        $this->webhooks->remove($webhook);
    }
}
