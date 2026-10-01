<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Application\Subscriptions;
use App\Billing\Domain\Error\NoScheduledChange;
use App\Shared\Domain\Iso;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ManageSubscriptionHandler
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly PaymentGateway $gateway,
    ) {
    }

    /**
     * @return array{subscription_id: string, plan_id: string|null, renews_at?: string|null, active_until?: string|null}
     */
    public function __invoke(ManageSubscription $command): array
    {
        $subscription = $this->subscriptions->require($command->customerId);
        $subscription = match ($command->action) {
            ManageSubscription::REVERT => $subscription->hasScheduledChange()
                ? $this->gateway->releaseSchedule($subscription->id)
                : throw new NoScheduledChange(),
            ManageSubscription::CANCEL => $this->gateway->cancelAtPeriodEnd($subscription->id),
            default => $this->gateway->resume($subscription->id),
        };
        $until = Iso::datetime($subscription->currentPeriodEnd);

        return ['subscription_id' => $subscription->id, 'plan_id' => $this->subscriptions->planIdOf($subscription, $command->customerId)]
            + (ManageSubscription::CANCEL === $command->action ? ['active_until' => $until] : ['renews_at' => $until]);
    }
}
