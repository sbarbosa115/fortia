<?php

namespace App\Billing\Application\Command;

/**
 * POST /checkout/plan-change (PRD §7.4, §8.3): an upgrade now, a downgrade at the end of the period, or a checkout
 * when the account has no subscription.
 */
final class ChangePlan
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $email,
        public readonly string $planId,
        public readonly string $billingInterval,
    ) {
    }
}
