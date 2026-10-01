<?php

namespace App\Billing\Application\Port;

/** What a hosted checkout is for (PRD §7.4): one subscription line, metadata, the return URLs and the trial. */
final class CheckoutRequest
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $planId,
        public readonly string $billingInterval,
        public readonly string $priceId,
        /** Reuse the gateway customer when the account already has one. */
        public readonly ?string $gatewayCustomerId,
        public readonly ?string $customerEmail,
        /** 0 = no trial. A card is always required; without one when the trial ends, the subscription is canceled. */
        public readonly int $trialDays,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
    ) {
    }

    /** @return array{customer_id: string, plan_id: string, billing_interval: string} */
    public function metadata(): array
    {
        return ['customer_id' => $this->customerId, 'plan_id' => $this->planId, 'billing_interval' => $this->billingInterval];
    }
}
