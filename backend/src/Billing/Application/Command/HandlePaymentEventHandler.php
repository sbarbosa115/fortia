<?php

namespace App\Billing\Application\Command;

use App\Billing\Application\PlanCatalog;
use App\Billing\Application\Port\GatewayEvent;
use App\Billing\Application\Port\GatewaySubscription;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Application\Usage;
use App\Billing\Domain\Event\PaymentFailed;
use App\Billing\Domain\Event\PlanChanged;
use App\Billing\Domain\Event\SubscriptionCancelled;
use App\Billing\Domain\Event\SubscriptionCreated;
use App\Billing\Domain\Event\SubscriptionRenewed;
use App\Billing\Domain\Event\TrialWillEnd;
use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Model\Payment;
use App\Billing\Domain\Model\ProcessedWebhookEvent;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Billing\Domain\Repository\PaymentRepository;
use App\Billing\Domain\Repository\ProcessedWebhookEventRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\Failure;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Applies a payment webhook event (PRD §7.4). Each event id is applied once: it is recorded with its effects in the
 * same transaction, so a redelivery is a no-op and a failure (an exception → 500) is retried by the gateway.
 *
 * | Event                                | Effect                                                               |
 * |--------------------------------------|----------------------------------------------------------------------|
 * | checkout.session.completed           | assign the plan with the subscription's period; SubscriptionCreated |
 * | invoice.paid                         | reassign the period; record the payment; renewal → SubscriptionRenewed |
 * | customer.subscription.updated        | reassign; plan changed → PlanChanged; pricier plan → reset usage   |
 * | customer.subscription.deleted        | SubscriptionCancelled; forget the subscription id (the window stays) |
 * | customer.subscription.trial_will_end | TrialWillEnd                                                         |
 * | invoice.payment_failed               | PaymentFailed                                                        |
 */
#[AsMessageHandler(bus: 'command.bus')]
final class HandlePaymentEventHandler
{
    public function __construct(
        private readonly ProcessedWebhookEventRepository $processed,
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PaymentRepository $payments,
        private readonly PaymentGateway $gateway,
        private readonly PlanCatalog $catalog,
        private readonly Usage $usage,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    /** @return 'applied'|'duplicate' */
    public function __invoke(HandlePaymentEvent $command): string
    {
        $event = $command->event;
        if ($this->processed->has($event->id)) {
            return 'duplicate';
        }

        match ($event->type) {
            GatewayEvent::CHECKOUT_COMPLETED => $this->checkoutCompleted($event),
            GatewayEvent::INVOICE_PAID => $this->invoicePaid($event),
            GatewayEvent::SUBSCRIPTION_UPDATED => $this->subscriptionUpdated($event),
            GatewayEvent::SUBSCRIPTION_DELETED => $this->subscriptionDeleted($event),
            GatewayEvent::TRIAL_WILL_END => $this->notify($event, TrialWillEnd::class),
            GatewayEvent::PAYMENT_FAILED => $this->notify($event, PaymentFailed::class),
            default => null,
        };

        $this->processed->add(new ProcessedWebhookEvent($event->id, $event->type, $this->clock->now()));

        return 'applied';
    }

    private function checkoutCompleted(GatewayEvent $event): void
    {
        $subscription = $this->liveSubscription($event);
        $plan = $this->sync($event, $subscription, withPlan: true);
        $this->events->publish(new SubscriptionCreated($plan->customerId(), null, [
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->planId(),
            'billing_interval' => $plan->billingInterval(),
        ]));
    }

    private function invoicePaid(GatewayEvent $event): void
    {
        $subscription = $this->liveSubscription($event);
        // Only the period: the plan itself changes with customer.subscription.updated, which decides on the reset.
        $plan = $this->sync($event, $subscription, withPlan: false);
        $this->payments->add(new Payment(
            $event->id,
            $plan->customerId(),
            $event->invoiceId,
            $subscription->id,
            $event->amount ?? 0,
            $event->currency ?? 'usd',
            $event->billingReason,
            $this->clock->now(),
        ));
        if ('subscription_cycle' === $event->billingReason) {
            $this->events->publish(new SubscriptionRenewed($plan->customerId(), null, [
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->planId(),
                'amount' => $event->amount,
                'currency' => $event->currency,
            ]));
        }
    }

    private function subscriptionUpdated(GatewayEvent $event): void
    {
        $subscription = $this->liveSubscription($event);
        if ($subscription->isCanceled()) {
            return;
        }
        $customerId = $this->customerOf($event, $subscription);
        $before = null === $customerId ? null : $this->customerPlans->find($customerId)?->planId();
        $plan = $this->sync($event, $subscription, withPlan: true);
        if (null === $before || $before === $plan->planId()) {
            return;
        }

        $from = $this->catalog->find($before);
        $to = $this->catalog->find($plan->planId());
        // PRD §7.4: a more expensive plan resets every usage counter of the period to 0.
        $pricier = null !== $to && (null === $from || ($to->priceAmount() ?? 0) > ($from->priceAmount() ?? 0));
        if ($pricier) {
            $this->usage->reset($plan->customerId());
        }
        $this->events->publish(new PlanChanged($plan->customerId(), null, [
            'from_plan_id' => $before,
            'to_plan_id' => $plan->planId(),
            'billing_interval' => $plan->billingInterval(),
            'usage_reset' => $pricier,
        ]));
    }

    private function subscriptionDeleted(GatewayEvent $event): void
    {
        $plan = null === $event->subscriptionId ? null : $this->customerPlans->findBySubscription($event->subscriptionId);
        $plan ??= $this->planOfMetadata($event);
        if (null === $plan) {
            return;
        }
        // The paid window (to_at) expires on its own; only the subscription id is forgotten.
        $plan->linkGateway($plan->stripeCustomerId() ?? $event->gatewayCustomerId, null, $this->clock->now());
        $this->events->publish(new SubscriptionCancelled($plan->customerId(), null, [
            'subscription_id' => $event->subscriptionId,
            'plan_id' => $plan->planId(),
        ]));
    }

    /** @param class-string<TrialWillEnd|PaymentFailed> $eventClass */
    private function notify(GatewayEvent $event, string $eventClass): void
    {
        $plan = null === $event->subscriptionId ? null : $this->customerPlans->findBySubscription($event->subscriptionId);
        $customerId = $event->metadata['customer_id'] ?? $plan?->customerId()
            ?? (null === $event->gatewayCustomerId ? null : $this->customerPlans->findByGatewayCustomer($event->gatewayCustomerId)?->customerId());
        if (null === $customerId) {
            return;
        }
        $this->events->publish(new $eventClass($customerId, null, array_filter([
            'subscription_id' => $event->subscriptionId,
            'invoice_id' => $event->invoiceId,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ], static fn ($value): bool => null !== $value)));
    }

    private function liveSubscription(GatewayEvent $event): GatewaySubscription
    {
        $subscription = null === $event->subscriptionId ? null : $this->gateway->subscription($event->subscriptionId);

        // A 500 makes the gateway retry (PRD §8.3).
        return $subscription ?? throw new Failure('SUBSCRIPTION_NOT_FOUND', "The gateway has no subscription '{$event->subscriptionId}'.");
    }

    /** Assigns the subscription's period (and plan) to its account; creates the account's plan row if needed. */
    private function sync(GatewayEvent $event, GatewaySubscription $subscription, bool $withPlan): CustomerPlan
    {
        $customerId = $this->customerOf($event, $subscription) ?? throw new Failure('CUSTOMER_NOT_FOUND', "No account for subscription '{$subscription->id}'.");
        $planId = $this->catalog->byPriceId($subscription->priceId)?->id() ?? $subscription->metadata['plan_id'] ?? null;
        $now = $this->clock->now();
        $fromAt = $subscription->currentPeriodStart->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $toAt = $subscription->currentPeriodEnd->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

        $plan = $this->customerPlans->find($customerId);
        if (null === $plan) {
            $plan = new CustomerPlan($customerId, $planId ?? throw new Failure('PLAN_NOT_FOUND', "No plan for price '{$subscription->priceId}'."), $fromAt, $toAt, $subscription->interval, $now);
            $this->customerPlans->add($plan);
        } elseif ($withPlan && null !== $planId) {
            $plan->assign($planId, $fromAt, $toAt, $subscription->interval, $now);
        } else {
            $plan->assign($plan->planId(), $fromAt, $toAt, $plan->billingInterval(), $now);
        }
        $plan->linkGateway($subscription->customerId, $subscription->id, $now);
        $plan->setTrialAndDiscount($subscription->trialEnd, $subscription->discount, $now);

        return $plan;
    }

    private function customerOf(GatewayEvent $event, GatewaySubscription $subscription): ?string
    {
        return $subscription->metadata['customer_id']
            ?? $event->metadata['customer_id']
            ?? $this->customerPlans->findBySubscription($subscription->id)?->customerId()
            ?? $this->customerPlans->findByGatewayCustomer($subscription->customerId)?->customerId();
    }

    private function planOfMetadata(GatewayEvent $event): ?CustomerPlan
    {
        $customerId = $event->metadata['customer_id'] ?? null;

        return null === $customerId ? null : $this->customerPlans->find($customerId);
    }
}
