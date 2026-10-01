<?php

namespace App\Billing\Application\Command;

/** POST /checkout/session (PRD §7.4, §8.3): a hosted checkout for a plan; returns its URL. No plan gate. */
final class StartCheckout
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $email,
        public readonly string $planId,
        public readonly string $billingInterval,
    ) {
    }
}
