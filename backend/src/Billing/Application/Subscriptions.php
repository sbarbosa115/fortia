<?php

namespace App\Billing\Application;

use App\Billing\Application\Port\GatewaySubscription;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Domain\Error\NoSubscription;
use App\Billing\Domain\Repository\CustomerPlanRepository;

/** An account's subscription, read live from the gateway (it is what is charged today, PRD §7.4). */
final class Subscriptions
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PaymentGateway $gateway,
        private readonly PlanCatalog $catalog,
    ) {
    }

    /** The account's live subscription, or null when it has none (or it was canceled). */
    public function of(string $customerId): ?GatewaySubscription
    {
        $subscriptionId = $this->customerPlans->find($customerId)?->stripeSubscriptionId();
        if (null === $subscriptionId) {
            return null;
        }
        $subscription = $this->gateway->subscription($subscriptionId);

        return null === $subscription || $subscription->isCanceled() ? null : $subscription;
    }

    /** @throws NoSubscription */
    public function require(string $customerId): GatewaySubscription
    {
        return $this->of($customerId) ?? throw new NoSubscription();
    }

    /** The plan id a subscription charges for: by its price, else its metadata, else the account's plan. */
    public function planIdOf(GatewaySubscription $subscription, ?string $customerId = null): ?string
    {
        return $this->catalog->byPriceId($subscription->priceId)?->id()
            ?? ($subscription->metadata['plan_id'] ?? null)
            ?? (null === $customerId ? null : $this->customerPlans->find($customerId)?->planId());
    }
}
