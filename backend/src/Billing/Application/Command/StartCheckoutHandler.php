<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\BillingUrls;
use App\Billing\Application\PlanCatalog;
use App\Billing\Application\Port\CheckoutRequest;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class StartCheckoutHandler
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PaymentGateway $gateway,
        private readonly BillingUrls $urls,
    ) {
    }

    /** @return string the hosted checkout URL */
    public function __invoke(StartCheckout $command): string
    {
        [$plan, $priceId] = $this->catalog->purchasable($command->planId, $command->billingInterval);
        $customerPlan = $this->customerPlans->find($command->customerId);
        // PRD §7.3: a trial only once per account, and only when the plan has trial days.
        $trialDays = $plan->trialDays() > 0 && null === $customerPlan?->trialUsedAt() ? $plan->trialDays() : 0;

        return $this->gateway->createCheckoutSession(new CheckoutRequest(
            $command->customerId,
            $plan->id(),
            $command->billingInterval,
            $priceId,
            $customerPlan?->stripeCustomerId(),
            $command->email,
            $trialDays,
            $this->urls->checkoutSuccess(),
            $this->urls->checkoutCancel(),
        ));
    }
}
