<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from invoice.paid with billing_reason = subscription_cycle. Payload: {subscription_id, plan_id, amount, currency}. */
final class SubscriptionRenewed extends BaseDomainEvent
{
}
