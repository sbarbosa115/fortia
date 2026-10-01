<?php

namespace App\Billing\Domain\Event;

use App\Shared\Domain\Event\BaseDomainEvent;

/** PRD §12, from customer.subscription.trial_will_end. Payload: {subscription_id}. */
final class TrialWillEnd extends BaseDomainEvent
{
}
