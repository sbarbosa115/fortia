<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\PlanCatalog;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Application\Subscriptions;
use App\Billing\Domain\Error\SamePlan;
use App\Billing\Domain\PlanChange;
use App\Billing\Domain\PlanChangeKind;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangePlanHandler
{
    public function __construct(
        private readonly PlanCatalog $catalog,
        private readonly Subscriptions $subscriptions,
        private readonly PaymentGateway $gateway,
        private readonly StartCheckoutHandler $checkout,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array{type: 'changed', plan_id: string, billing_interval: string, change: string, effective_at: string|null}|array{type: 'checkout', plan_id: string, billing_interval: string, checkout_url: string}
     */
    public function __invoke(ChangePlan $command): array
    {
        [$plan, $priceId] = $this->catalog->purchasable($command->planId, $command->billingInterval);
        $subscription = $this->subscriptions->of($command->customerId);
        if (null === $subscription) {
            $url = ($this->checkout)(new StartCheckout($command->customerId, $command->email, $plan->id(), $command->billingInterval));

            return ['type' => 'checkout', 'plan_id' => $plan->id(), 'billing_interval' => $command->billingInterval, 'checkout_url' => $url];
        }
        if ($subscription->priceId === $priceId) {
            throw new SamePlan();
        }

        // Compared against the plan the gateway is actually charging today (PRD §7.4).
        $current = $this->catalog->byPriceId($subscription->priceId);
        $kind = PlanChange::classify($current?->priceAmount(), $subscription->interval, $plan->priceAmount(), $command->billingInterval);
        $metadata = ['customer_id' => $command->customerId, 'plan_id' => $plan->id(), 'billing_interval' => $command->billingInterval];
        if (PlanChangeKind::Upgrade === $kind) {
            $this->gateway->changePrice($subscription->id, $priceId, $metadata);
            $effectiveAt = $this->clock->now();
        } else {
            $changed = $this->gateway->scheduleChange($subscription->id, $priceId, $metadata);
            $effectiveAt = $changed->currentPeriodEnd;
        }

        return [
            'type' => 'changed',
            'plan_id' => $plan->id(),
            'billing_interval' => $command->billingInterval,
            'change' => $kind->value,
            'effective_at' => Iso::datetime($effectiveAt),
        ];
    }
}
