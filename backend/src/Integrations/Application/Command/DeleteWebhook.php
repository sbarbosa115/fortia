<?php

namespace App\Integrations\Application\Command;

use App\Shared\Application\Security\Caller;

/** DELETE /webhooks/{id} (PRD §8.11): removes the webhook and its delivery log. 404 WEBHOOK_NOT_FOUND otherwise. */
final class DeleteWebhook
{
    public function __construct(
        public readonly Caller $caller,
        public readonly string $webhookId,
    ) {
    }
}
