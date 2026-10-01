<?php

namespace App\Integrations\Infrastructure\Scheduler;

use App\Integrations\Application\RetryDueWebhooks;
use Symfony\Component\Scheduler\Attribute\AsPeriodicTask;

/** D19: every minute the worker retries the webhook deliveries whose backoff has passed (scheduler_default). */
#[AsPeriodicTask(frequency: '1 minute')]
final class WebhookRetriesTask
{
    public function __construct(private readonly RetryDueWebhooks $retries)
    {
    }

    public function __invoke(): void
    {
        $this->retries->run();
    }
}
