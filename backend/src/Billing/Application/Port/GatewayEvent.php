<?php

namespace App\Billing\Application\Port;

/**
 * A verified webhook event, read into what the handler needs (PRD §7.4 "Payment webhook events"). The adapters
 * normalize the gateway's own shapes into this one.
 */
final class GatewayEvent
{
    public const CHECKOUT_COMPLETED = 'checkout.session.completed';
    public const INVOICE_PAID = 'invoice.paid';
    public const SUBSCRIPTION_UPDATED = 'customer.subscription.updated';
    public const SUBSCRIPTION_DELETED = 'customer.subscription.deleted';
    public const TRIAL_WILL_END = 'customer.subscription.trial_will_end';
    public const PAYMENT_FAILED = 'invoice.payment_failed';

    /**
     * @param array<string, string> $metadata the subscription's (or checkout's) metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $subscriptionId,
        public readonly ?string $gatewayCustomerId,
        public readonly array $metadata = [],
        /** Invoices: subscription_create, subscription_cycle, subscription_update… */
        public readonly ?string $billingReason = null,
        public readonly ?string $invoiceId = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
    ) {
    }
}
