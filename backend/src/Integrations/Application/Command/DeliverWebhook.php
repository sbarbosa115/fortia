<?php

namespace App\Integrations\Application\Command;

/** Retries one pending webhook delivery whose next attempt is due (D19). Nothing happens when it is not due. */
final class DeliverWebhook
{
    public function __construct(public readonly string $deliveryId)
    {
    }
}
