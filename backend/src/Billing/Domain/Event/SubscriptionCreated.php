<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from checkout.session.completed. Payload: {subscription_id, plan_id, billing_interval}. */
final class SubscriptionCreated extends BaseDomainEvent
{
}
