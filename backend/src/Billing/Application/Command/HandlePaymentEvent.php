<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\GatewayEvent;

/** A verified payment webhook event (PRD §7.4 "Payment webhook events", all idempotent). */
final class HandlePaymentEvent
{
    public function __construct(public readonly GatewayEvent $event)
    {
    }
}
