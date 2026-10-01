<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from customer.subscription.deleted. Payload: {subscription_id, plan_id}. */
final class SubscriptionCancelled extends BaseDomainEvent
{
}
