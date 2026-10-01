<?php

namespace App\Billing\Application\Port;

/**
 * The fake gateway's own pages (/fake-gateway/*): the hosted checkout with "Pay" and "Cancel", and a billing portal
 * that can also simulate a renewal. Only the fake adapter implements it; with PAYMENT_PROVIDER=stripe enabled() is
 * false and the pages answer 404. Paying queues signed webhook events that reach POST /checkout/webhook before the
 * browser is redirected back.
 */
interface SimulatedGateway
{
    public function enabled(): bool;

    /**
     * @return array{id: string, status: string, plan_name: string, amount: int, currency: string, interval: string,
     *               trial_days: int, customer_email: ?string, success_url: string, cancel_url: string}|null
     */
    public function checkoutSession(string $sessionId): ?array;

    /**
     * Pays an open checkout: creates the gateway customer (if new) and the subscription, and queues
     * checkout.session.completed and invoice.paid. Returns the success URL.
     *
     * @throws GatewayUnavailable for an unknown or expired promotion code, or a session that is not open
     */
    public function payCheckout(string $sessionId, ?string $promotionCode): string;

    /** Abandons an open checkout; returns the cancel URL. */
    public function cancelCheckout(string $sessionId): string;

    /**
     * @return array{id: string, return_url: string, subscriptions: list<array{id: string, status: string,
     *               price_id: string, interval: string, current_period_end: string, cancel_at_period_end: bool,
     *               scheduled_price_id: ?string}>}|null
     */
    public function portalSession(string $portalId): ?array;

    /**
     * Renews a subscription now, as the gateway would on its renewal date: applies a scheduled change (or ends a
     * subscription canceled at period end), starts a new period today, and queues the webhook events of that.
     */
    public function renew(string $subscriptionId): void;
}
