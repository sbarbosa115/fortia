<?php

namespace App\Billing\UI\Http\Output;

/** POST /checkout/cancel (PRD §8.3): canceled at period end, active until then. */
final class SubscriptionCancelOutput
{
    public function __construct(
        public readonly string $subscription_id,
        public readonly ?string $plan_id,
        public readonly ?string $active_until,
    ) {
    }
}
