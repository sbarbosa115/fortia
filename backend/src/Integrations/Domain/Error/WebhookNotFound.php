<?php

namespace App\Integrations\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** 404: no such webhook, or it belongs to another account (PRD §8.11). */
final class WebhookNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('WEBHOOK_NOT_FOUND', 'Webhook not found.');
    }
}
