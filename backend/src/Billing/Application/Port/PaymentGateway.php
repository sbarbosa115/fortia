<?php

namespace App\Billing\Application\Port;

/**
 * The payment gateway (PRD §13.2, rules in §7.4): hosted checkout, subscriptions (immediate price changes with
 * proration, scheduled downgrades, cancel at period end, resume), the billing portal, prices, coupons and promotion
 * codes, and signed webhooks.
 *
 * Two adapters (config/services/billing.yaml, PAYMENT_PROVIDER): Stripe, and a deterministic fake that keeps its
 * state in its own tables and serves a local hosted-checkout page (/fake-gateway/*) for dev and tests.
 *
 * Every method throws GatewayUnavailable (502 STRIPE_UNAVAILABLE) when the gateway refuses or cannot be reached.
 */
interface PaymentGateway
{
    /** A subscription with a single line item on the gateway's hosted page; returns the page's URL. */
    public function createCheckoutSession(CheckoutRequest $request): string;

    /** The subscription as the gateway sees it now, or null when it does not exist. */
    public function subscription(string $subscriptionId): ?GatewaySubscription;

    /**
     * An upgrade: the new price applies right away and the prorated difference is charged immediately. When that
     * charge fails the change fails (and nothing changes). Releases a pending schedule first.
     *
     * @param array<string, string> $metadata {customer_id, plan_id, billing_interval}
     */
    public function changePrice(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription;

    /**
     * A downgrade: $priceId becomes the next phase for one cycle, after which the schedule is released and the
     * subscription renews on it.
     *
     * @param array<string, string> $metadata {customer_id, plan_id, billing_interval}
     */
    public function scheduleChange(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription;

    /** Undoes a scheduled change (revert). A subscription without one is returned unchanged. */
    public function releaseSchedule(string $subscriptionId): GatewaySubscription;

    /** Cancel = cancel at period end (the paid window stays). Releases a pending schedule first. */
    public function cancelAtPeriodEnd(string $subscriptionId): GatewaySubscription;

    /** Removes the cancellation at period end; idempotent. Fails when the period already expired. */
    public function resume(string $subscriptionId): GatewaySubscription;

    /** The gateway's billing portal for one of its customers; returns its URL. */
    public function createPortalSession(string $gatewayCustomerId, string $returnUrl): string;

    /** A price by its id, or null when the gateway does not know it (admin plan validation, INVALID_STRIPE_PRICE). */
    public function price(string $priceId): ?GatewayPrice;

    public function createCoupon(CouponRequest $request): GatewayCoupon;

    public function deleteCoupon(string $couponId): void;

    public function createPromotionCode(PromotionCodeRequest $request): GatewayPromotionCode;

    /** Deactivates a promotion code (DELETE /admin/coupons/{promotion_code_id}); null when it does not exist. */
    public function deactivatePromotionCode(string $promotionCodeId): ?GatewayPromotionCode;

    /** @return list<GatewayPromotionCode> every promotion code, newest first, with its coupon */
    public function promotionCodes(): array;

    /** The promotion code with this exact code (case-sensitive, codes are uppercased), active or not. */
    public function promotionCodeByCode(string $code): ?GatewayPromotionCode;

    /**
     * Verifies a webhook's signature and reads the event.
     *
     * @throws InvalidWebhookSignature
     */
    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent;
}
