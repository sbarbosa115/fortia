<?php

namespace App\Integrations\Application\Port;

/** The secret outgoing webhooks are signed with (PRD §7.14, §13 secrets: WEBHOOK_SIGNING_SECRET). */
interface WebhookSigningSecret
{
    public function value(): string;
}
