<?php

namespace App\Integrations\Application\Command;

use App\Integrations\Application\WebhookPayload;
use App\Integrations\Domain\Error\WebhookNotFound;
use App\Integrations\Domain\Model\WebhookSubscription;
use App\Integrations\Domain\Repository\WebhookRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveWebhookHandler
{
    public function __construct(
        private readonly WebhookRepository $webhooks,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveWebhook $command): string
    {
        $now = $this->clock->now();
        $fields = $command->fields;
        if (null === $command->webhookId) {
            $id = Ids::uuid4();
            $this->webhooks->add(new WebhookSubscription(
                $id,
                $command->caller->customerId,
                trim($fields['url'] ?? ''),
                $fields['event_type'] ?? WebhookPayload::QUESTIONNAIRE_COMPLETED,
                $fields['method'] ?? 'POST',
                $now,
            ));

            return $id;
        }

        $webhook = $this->webhooks->find($command->webhookId);
        if (null === $webhook || !$command->caller->owns($webhook->customerId())) {
            throw new WebhookNotFound();
        }
        $webhook->change(
            isset($fields['url']) ? trim($fields['url']) : $webhook->url(),
            $fields['event_type'] ?? $webhook->eventType(),
            $fields['method'] ?? $webhook->method(),
            $now,
        );

        return $webhook->id();
    }
}
