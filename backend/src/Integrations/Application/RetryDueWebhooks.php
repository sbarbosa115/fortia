<?php

namespace App\Integrations\Application;

use App\Integrations\Application\Command\DeliverWebhook;
use App\Integrations\Domain\Repository\WebhookDeliveryRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Clock;

/**
 * D19: retries the pending deliveries whose backoff has passed, one command (one transaction) each. Run every
 * minute on the worker (Infrastructure\Scheduler\WebhookRetriesTask) or by hand with app:webhooks:retry.
 */
final class RetryDueWebhooks
{
    private const BATCH = 100;

    public function __construct(
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly CommandBus $commands,
        private readonly Clock $clock,
    ) {
    }

    /** @return int how many deliveries were attempted */
    public function run(): int
    {
        $ids = $this->deliveries->dueIds($this->clock->now(), self::BATCH);
        foreach ($ids as $id) {
            $this->commands->dispatch(new DeliverWebhook($id));
        }

        return \count($ids);
    }
}
