<?php

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\ProcessedWebhookEvent;

interface ProcessedWebhookEventRepository
{
    public function has(string $eventId): bool;

    public function add(ProcessedWebhookEvent $event): void;
}
