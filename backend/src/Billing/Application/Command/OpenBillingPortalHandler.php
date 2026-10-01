<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\BillingUrls;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Domain\Error\NoStripeCustomer;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class OpenBillingPortalHandler
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PaymentGateway $gateway,
        private readonly BillingUrls $urls,
    ) {
    }

    /** @return string the portal URL */
    public function __invoke(OpenBillingPortal $command): string
    {
        $gatewayCustomer = $this->customerPlans->find($command->customerId)?->stripeCustomerId() ?? throw new NoStripeCustomer();

        return $this->gateway->createPortalSession($gatewayCustomer, $this->urls->plans());
    }
}
