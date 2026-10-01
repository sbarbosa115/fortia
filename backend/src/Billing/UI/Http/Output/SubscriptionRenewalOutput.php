<?php

namespace App\Billing\UI\Http\Output;

/** POST /checkout/plan-change/revert and /checkout/resume (PRD §8.3): the subscription renews on this plan. */
final class SubscriptionRenewalOutput
{
    public function __construct(
        public readonly string $subscription_id,
        public readonly ?string $plan_id,
        public readonly ?string $renews_at,
    ) {
    }
}
