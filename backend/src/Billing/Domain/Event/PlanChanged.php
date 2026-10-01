<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from customer.subscription.updated when the plan changed. Payload: {from_plan_id, to_plan_id, billing_interval, usage_reset}. */
final class PlanChanged extends BaseDomainEvent
{
}
