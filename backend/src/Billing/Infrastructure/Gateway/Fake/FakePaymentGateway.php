<?php

namespace App\Billing\Infrastructure\Gateway\Fake;

use App\Billing\Application\Port\CheckoutRequest;
use App\Billing\Application\Port\CouponRequest;
use App\Billing\Application\Port\GatewayCoupon;
use App\Billing\Application\Port\GatewayEvent;
use App\Billing\Application\Port\GatewayPrice;
use App\Billing\Application\Port\GatewayPromotionCode;
use App\Billing\Application\Port\GatewaySubscription;
use App\Billing\Application\Port\GatewayUnavailable;
use App\Billing\Application\Port\InvalidWebhookSignature;
use App\Billing\Application\Port\PaymentGateway;
use App\Billing\Application\Port\PromotionCodeRequest;
use App\Billing\Application\Port\SimulatedGateway;
use App\Billing\Domain\Repository\PlanRepository;
use App\Billing\Infrastructure\Gateway\StripeEventReader;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use App\Shared\Domain\Iso;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A deterministic payment gateway for dev and tests (PAYMENT_PROVIDER=fake). It keeps its state in its own tables
 * (fake_gateway_object, fake_gateway_event) as Stripe-shaped JSON, serves a local hosted checkout and portal
 * (/fake-gateway/*, FakeGatewayController), and sends signed Stripe-shaped webhook events to our own handler
 * (FakeWebhookDelivery), so the whole billing flow runs offline.
 *
 * Prices: the plan catalog's gateway price ids (price_fake_pro_month…), prices defined with definePrice(), and any id
 * shaped price_fake_<name>_<month|year>_<amount>[_<currency>].
 *
 * It writes with DBAL, outside our unit of work: like a real gateway, what it did is not undone by our rollback.
 */
final class FakePaymentGateway implements PaymentGateway, SimulatedGateway
{
    public const SIGNATURE_HEADER = 'Stripe-Signature';

    public function __construct(
        private readonly Connection $db,
        private readonly PlanRepository $plans,
        private readonly Clock $clock,
        #[Autowire(env: 'PAYMENT_PROVIDER')]
        private readonly string $provider,
        #[Autowire('%kernel.secret%')]
        private readonly string $signingSecret,
        #[Autowire('%app.url%')]
        private readonly string $appUrl,
    ) {
    }

    public function enabled(): bool
    {
        return 'stripe' !== $this->provider;
    }

    // ── Checkout ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function createCheckoutSession(CheckoutRequest $request): string
    {
        $price = $this->price($request->priceId) ?? throw GatewayUnavailable::because("No such price: '{$request->priceId}'.");
        if (null !== $request->gatewayCustomerId && null === $this->load('customer', $request->gatewayCustomerId)) {
            throw GatewayUnavailable::because("No such customer: '{$request->gatewayCustomerId}'.");
        }
        $plan = $this->plans->find($request->planId);
        $id = 'cs_fake_'.Ids::hex(24);
        $this->save('checkout_session', $id, [
            'id' => $id,
            'object' => 'checkout.session',
            'mode' => 'subscription',
            'status' => 'open',
            'customer' => $request->gatewayCustomerId,
            'customer_email' => $request->customerEmail,
            'price_id' => $price->id,
            'amount' => $price->amount ?? 0,
            'currency' => $price->currency,
            'interval' => $price->interval ?? $request->billingInterval,
            'plan_name' => $plan?->planName() ?? $request->planId,
            'trial_days' => max(0, $request->trialDays),
            'allow_promotion_codes' => true,
            'metadata' => $request->metadata(),
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'subscription' => null,
        ]);

        return rtrim($this->appUrl, '/').'/fake-gateway/checkout/'.$id;
    }

    public function checkoutSession(string $sessionId): ?array
    {
        $session = $this->load('checkout_session', $sessionId);
        if (null === $session) {
            return null;
        }

        return [
            'id' => (string) $session['id'],
            'status' => (string) $session['status'],
            'plan_name' => (string) $session['plan_name'],
            'amount' => (int) $session['amount'],
            'currency' => (string) $session['currency'],
            'interval' => (string) $session['interval'],
            'trial_days' => (int) $session['trial_days'],
            'customer_email' => isset($session['customer_email']) ? (string) $session['customer_email'] : null,
            'success_url' => (string) $session['success_url'],
            'cancel_url' => (string) $session['cancel_url'],
        ];
    }

    public function payCheckout(string $sessionId, ?string $promotionCode): string
    {
        $session = $this->load('checkout_session', $sessionId);
        if (null === $session || 'open' !== $session['status']) {
            throw GatewayUnavailable::because('This checkout is no longer open.');
        }
        $price = $this->price((string) $session['price_id']) ?? throw GatewayUnavailable::because('The price no longer exists.');
        $now = $this->clock->now();
        $discount = null;
        $promotionCode = null === $promotionCode ? '' : strtoupper(trim($promotionCode));
        if ('' !== $promotionCode) {
            $discount = $this->redeem($promotionCode, $price, $now);
        }

        $customerId = $session['customer'] ?? null;
        if (!\is_string($customerId)) {
            $customerId = 'cus_fake_'.Ids::hex(14);
            $this->save('customer', $customerId, ['id' => $customerId, 'object' => 'customer', 'email' => $session['customer_email'] ?? null]);
        }

        $trialDays = (int) $session['trial_days'];
        $interval = (string) $session['interval'];
        $end = $trialDays > 0 ? $now->modify("+{$trialDays} days") : self::addInterval($now, $interval);
        $subscriptionId = 'sub_fake_'.Ids::hex(14);
        $subscription = [
            'id' => $subscriptionId,
            'object' => 'subscription',
            'customer' => $customerId,
            'status' => $trialDays > 0 ? 'trialing' : 'active',
            'price_id' => $price->id,
            'interval' => $interval,
            'current_period_start' => $now->getTimestamp(),
            'current_period_end' => $end->getTimestamp(),
            'cancel_at_period_end' => false,
            'trial_end' => $trialDays > 0 ? $end->getTimestamp() : null,
            'schedule' => null,
            'metadata' => $session['metadata'],
            'discount' => $discount,
        ];
        $this->save('subscription', $subscriptionId, $subscription);
        $session['status'] = 'complete';
        $session['customer'] = $customerId;
        $session['subscription'] = $subscriptionId;
        $this->save('checkout_session', $sessionId, $session);

        $this->emit('customer.subscription.created', $subscription);
        $this->emit(GatewayEvent::CHECKOUT_COMPLETED, [
            'id' => $sessionId,
            'object' => 'checkout.session',
            'customer' => $customerId,
            'subscription' => $subscriptionId,
            'metadata' => $session['metadata'],
        ]);
        $this->emitInvoice(GatewayEvent::INVOICE_PAID, $subscription, 'subscription_create', $trialDays > 0 ? 0 : self::discounted($price->amount ?? 0, $discount));

        return (string) $session['success_url'];
    }

    public function cancelCheckout(string $sessionId): string
    {
        $session = $this->load('checkout_session', $sessionId) ?? throw GatewayUnavailable::because('No such checkout.');
        if ('open' === $session['status']) {
            $session['status'] = 'expired';
            $this->save('checkout_session', $sessionId, $session);
        }

        return (string) $session['cancel_url'];
    }

    // ── Subscriptions ────────────────────────────────────────────────────────────────────────────────────────────

    public function subscription(string $subscriptionId): ?GatewaySubscription
    {
        $data = $this->load('subscription', $subscriptionId);

        return null === $data ? null : self::toSubscription($data);
    }

    public function changePrice(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription
    {
        $subscription = $this->live($subscriptionId);
        $price = $this->price($priceId) ?? throw GatewayUnavailable::because("No such price: '{$priceId}'.");
        $customer = $this->load('customer', (string) $subscription['customer']) ?? [];
        if (true === ($customer['declines'] ?? false)) {
            throw GatewayUnavailable::because('The card was declined: the plan did not change.');
        }
        $old = $this->price((string) $subscription['price_id']);
        $now = $this->clock->now();
        $proration = 0;
        if (($price->interval ?? 'month') !== $subscription['interval']) {
            // A new interval restarts the billing period now and charges the new price in full.
            $subscription['current_period_start'] = $now->getTimestamp();
            $subscription['current_period_end'] = self::addInterval($now, $price->interval ?? 'month')->getTimestamp();
            $proration = $price->amount ?? 0;
        } else {
            $length = max(1, (int) $subscription['current_period_end'] - (int) $subscription['current_period_start']);
            $left = max(0, (int) $subscription['current_period_end'] - $now->getTimestamp());
            $proration = (int) round((($price->amount ?? 0) - ($old->amount ?? 0)) * $left / $length);
        }
        $subscription['price_id'] = $price->id;
        $subscription['interval'] = $price->interval ?? 'month';
        $subscription['schedule'] = null;
        $subscription['metadata'] = $metadata;
        if ('trialing' === $subscription['status']) {
            $subscription['status'] = 'active';
        }
        $this->save('subscription', $subscriptionId, $subscription);
        $this->emitInvoice(GatewayEvent::INVOICE_PAID, $subscription, 'subscription_update', max(0, $proration));
        $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);

        return self::toSubscription($subscription);
    }

    public function scheduleChange(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription
    {
        $subscription = $this->live($subscriptionId);
        $price = $this->price($priceId) ?? throw GatewayUnavailable::because("No such price: '{$priceId}'.");
        $subscription['schedule'] = [
            'id' => \is_array($subscription['schedule']) ? $subscription['schedule']['id'] : 'sub_sched_fake_'.Ids::hex(14),
            'price_id' => $price->id,
            'interval' => $price->interval ?? 'month',
            'metadata' => $metadata,
        ];
        $this->save('subscription', $subscriptionId, $subscription);
        $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);

        return self::toSubscription($subscription);
    }

    public function releaseSchedule(string $subscriptionId): GatewaySubscription
    {
        $subscription = $this->live($subscriptionId);
        if (null !== $subscription['schedule']) {
            $subscription['schedule'] = null;
            $this->save('subscription', $subscriptionId, $subscription);
            $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);
        }

        return self::toSubscription($subscription);
    }

    public function cancelAtPeriodEnd(string $subscriptionId): GatewaySubscription
    {
        $subscription = $this->live($subscriptionId);
        $subscription['schedule'] = null;
        $subscription['cancel_at_period_end'] = true;
        $this->save('subscription', $subscriptionId, $subscription);
        $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);

        return self::toSubscription($subscription);
    }

    public function resume(string $subscriptionId): GatewaySubscription
    {
        $subscription = $this->live($subscriptionId);
        if ((int) $subscription['current_period_end'] < $this->clock->now()->getTimestamp()) {
            throw GatewayUnavailable::because('The subscription period has already ended.');
        }
        if (true === $subscription['cancel_at_period_end']) {
            $subscription['cancel_at_period_end'] = false;
            $this->save('subscription', $subscriptionId, $subscription);
            $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);
        }

        return self::toSubscription($subscription);
    }

    public function renew(string $subscriptionId): void
    {
        $subscription = $this->live($subscriptionId);
        if (true === $subscription['cancel_at_period_end']) {
            $subscription['status'] = 'canceled';
            $this->save('subscription', $subscriptionId, $subscription);
            $this->emit(GatewayEvent::SUBSCRIPTION_DELETED, $subscription);

            return;
        }
        if (\is_array($subscription['schedule'])) {
            // The scheduled phase starts, then the schedule is released (PRD §7.4 downgrade).
            $subscription['price_id'] = $subscription['schedule']['price_id'];
            $subscription['interval'] = $subscription['schedule']['interval'];
            $subscription['metadata'] = $subscription['schedule']['metadata'];
            $subscription['schedule'] = null;
        }
        // The renewal happens now (the fake does not wait for the period end): the new period starts today, so the
        // account's plan window stays current in dev without moving the clock.
        $start = $this->clock->now();
        $subscription['current_period_start'] = $start->getTimestamp();
        $subscription['current_period_end'] = self::addInterval($start, (string) $subscription['interval'])->getTimestamp();
        $subscription['status'] = 'active';
        $this->save('subscription', $subscriptionId, $subscription);
        $price = $this->price((string) $subscription['price_id']);
        $this->emitInvoice(GatewayEvent::INVOICE_PAID, $subscription, 'subscription_cycle', self::discounted($price->amount ?? 0, $subscription['discount']));
        $this->emit(GatewayEvent::SUBSCRIPTION_UPDATED, $subscription);
    }

    // ── Portal ───────────────────────────────────────────────────────────────────────────────────────────────────

    public function createPortalSession(string $gatewayCustomerId, string $returnUrl): string
    {
        if (null === $this->load('customer', $gatewayCustomerId)) {
            throw GatewayUnavailable::because("No such customer: '{$gatewayCustomerId}'.");
        }
        $id = 'bps_fake_'.Ids::hex(14);
        $this->save('portal_session', $id, ['id' => $id, 'object' => 'billing_portal.session', 'customer' => $gatewayCustomerId, 'return_url' => $returnUrl]);

        return rtrim($this->appUrl, '/').'/fake-gateway/portal/'.$id;
    }

    public function portalSession(string $portalId): ?array
    {
        $portal = $this->load('portal_session', $portalId);
        if (null === $portal) {
            return null;
        }
        $subscriptions = [];
        foreach ($this->all('subscription') as $subscription) {
            if ($subscription['customer'] === $portal['customer']) {
                $subscriptions[] = [
                    'id' => (string) $subscription['id'],
                    'status' => (string) $subscription['status'],
                    'price_id' => (string) $subscription['price_id'],
                    'interval' => (string) $subscription['interval'],
                    'current_period_end' => gmdate('Y-m-d', (int) $subscription['current_period_end']),
                    'cancel_at_period_end' => (bool) $subscription['cancel_at_period_end'],
                    'scheduled_price_id' => \is_array($subscription['schedule']) ? (string) $subscription['schedule']['price_id'] : null,
                ];
            }
        }

        return ['id' => $portalId, 'return_url' => (string) $portal['return_url'], 'subscriptions' => $subscriptions];
    }

    // ── Prices, coupons, promotion codes ─────────────────────────────────────────────────────────────────────────

    public function price(string $priceId): ?GatewayPrice
    {
        $stored = $this->load('price', $priceId);
        if (null !== $stored) {
            return new GatewayPrice($priceId, $stored['amount'], (string) $stored['currency'], $stored['interval'], (bool) $stored['active'], $stored['product']);
        }
        foreach ($this->plans->all() as $plan) {
            if ($plan->stripePriceId() === $priceId) {
                return new GatewayPrice($priceId, $plan->priceAmount(), $plan->currency(), 'month', true, 'prod_fake_'.$plan->id());
            }
            if ($plan->stripeYearlyPriceId() === $priceId) {
                return new GatewayPrice($priceId, $plan->yearlyPriceAmount(), $plan->currency(), 'year', true, 'prod_fake_'.$plan->id());
            }
        }
        if (1 === preg_match('/^price_fake_([a-z0-9-]+)_(month|year)_(\d+)(?:_([a-z]{3}))?$/', $priceId, $m)) {
            return new GatewayPrice($priceId, (int) $m[3], $m[4] ?? 'usd', $m[2], true, 'prod_fake_'.$m[1]);
        }

        return null;
    }

    /** A price the fake knows from now on (tests of price validation). */
    public function definePrice(string $priceId, ?int $amount, string $currency, ?string $interval, bool $active = true, ?string $productId = null): void
    {
        $this->save('price', $priceId, ['id' => $priceId, 'amount' => $amount, 'currency' => strtolower($currency), 'interval' => $interval, 'active' => $active, 'product' => $productId]);
    }

    public function createCoupon(CouponRequest $request): GatewayCoupon
    {
        $id = 'coup_fake_'.Ids::hex(12);
        $data = [
            'id' => $id,
            'object' => 'coupon',
            'name' => $request->name,
            'percent_off' => $request->percentOff,
            'amount_off' => $request->amountOff,
            'currency' => null === $request->currency ? null : strtolower($request->currency),
            'duration' => $request->duration,
            'duration_in_months' => $request->durationInMonths,
            'product_ids' => $request->productIds,
            'valid' => true,
        ];
        $this->save('coupon', $id, $data);

        return self::toCoupon($data);
    }

    public function deleteCoupon(string $couponId): void
    {
        $coupon = $this->load('coupon', $couponId) ?? throw GatewayUnavailable::because("No such coupon: '{$couponId}'.");
        $coupon['valid'] = false;
        $this->save('coupon', $couponId, $coupon);
    }

    public function createPromotionCode(PromotionCodeRequest $request): GatewayPromotionCode
    {
        $coupon = $this->load('coupon', $request->couponId);
        if (null === $coupon || true !== $coupon['valid']) {
            throw GatewayUnavailable::because("No such coupon: '{$request->couponId}'.");
        }
        $existing = $this->promotionCodeByCode($request->code);
        if (null !== $existing && $existing->active) {
            throw GatewayUnavailable::because('An active promotion code with that code already exists.');
        }
        $id = 'promo_fake_'.Ids::hex(12);
        $this->save('promotion_code', $id, [
            'id' => $id,
            'object' => 'promotion_code',
            'code' => $request->code,
            'active' => true,
            'coupon' => $request->couponId,
            'expires_at' => $request->expiresAt?->getTimestamp(),
            'max_redemptions' => $request->maxRedemptions,
            'times_redeemed' => 0,
            'metadata' => $request->metadata,
            'created' => $this->clock->now()->getTimestamp(),
        ]);

        return $this->toPromotionCode($this->load('promotion_code', $id) ?? []);
    }

    public function deactivatePromotionCode(string $promotionCodeId): ?GatewayPromotionCode
    {
        $code = $this->load('promotion_code', $promotionCodeId);
        if (null === $code) {
            return null;
        }
        $code['active'] = false;
        $this->save('promotion_code', $promotionCodeId, $code);

        return $this->toPromotionCode($code);
    }

    public function promotionCodes(): array
    {
        return array_map(fn (array $code): GatewayPromotionCode => $this->toPromotionCode($code), $this->all('promotion_code'));
    }

    public function promotionCodeByCode(string $code): ?GatewayPromotionCode
    {
        foreach ($this->all('promotion_code') as $promotion) {
            if ($promotion['code'] === $code && true === $promotion['active']) {
                return $this->toPromotionCode($promotion);
            }
        }
        foreach ($this->all('promotion_code') as $promotion) {
            if ($promotion['code'] === $code) {
                return $this->toPromotionCode($promotion);
            }
        }

        return null;
    }

    // ── Webhooks ─────────────────────────────────────────────────────────────────────────────────────────────────

    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent
    {
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }
        $timestamp = $parts['t'] ?? '';
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $this->signingSecret);
        if ('' === $timestamp || !hash_equals($expected, $parts['v1'] ?? '')) {
            throw new InvalidWebhookSignature('The webhook signature does not match.');
        }
        try {
            $event = json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidWebhookSignature('The webhook body is not JSON.', 0, $e);
        }

        return StripeEventReader::read(\is_array($event) ? $event : []);
    }

    /** The Stripe-Signature header for a payload, signed with the fake's secret (delivery, tests). */
    public function sign(string $payload): string
    {
        $timestamp = (string) $this->clock->now()->getTimestamp();

        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $this->signingSecret);
    }

    /**
     * A Stripe-shaped event body for $object (tests post it to the webhook themselves).
     *
     * @param array<string, mixed> $object
     */
    public function eventPayload(string $type, array $object, ?string $eventId = null): string
    {
        return (string) json_encode([
            'id' => $eventId ?? 'evt_fake_'.Ids::hex(20),
            'object' => 'event',
            'type' => $type,
            'created' => $this->clock->now()->getTimestamp(),
            'data' => ['object' => $object],
        ], \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }

    /** @return list<array{seq: int, event_id: string, payload: string}> the events not delivered yet, in order */
    public function pendingEvents(): array
    {
        /** @var list<array{seq: int|string, event_id: string, payload: string}> $rows */
        $rows = $this->db->fetchAllAssociative('SELECT seq, event_id, payload FROM fake_gateway_event WHERE delivered_at IS NULL ORDER BY seq');

        return array_map(static fn (array $row): array => ['seq' => (int) $row['seq'], 'event_id' => (string) $row['event_id'], 'payload' => (string) $row['payload']], $rows);
    }

    public function markDelivered(int $seq, int $status): void
    {
        $this->db->executeStatement('UPDATE fake_gateway_event SET delivered_at = ?, response_status = ? WHERE seq = ?', [
            $this->clock->now()->format('Y-m-d H:i:s'), $status, $seq,
        ]);
    }

    /** From now on, charges to this gateway customer fail (tests of "if that charge fails, the change fails"). */
    public function declineCards(string $gatewayCustomerId): void
    {
        $customer = $this->load('customer', $gatewayCustomerId) ?? ['id' => $gatewayCustomerId, 'object' => 'customer'];
        $customer['declines'] = true;
        $this->save('customer', $gatewayCustomerId, $customer);
    }

    // ── Internals ────────────────────────────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> a subscription that exists and is not canceled */
    private function live(string $subscriptionId): array
    {
        $subscription = $this->load('subscription', $subscriptionId);
        if (null === $subscription) {
            throw GatewayUnavailable::because("No such subscription: '{$subscriptionId}'.");
        }
        if ('canceled' === $subscription['status']) {
            throw GatewayUnavailable::because('The subscription is canceled.');
        }

        return $subscription;
    }

    /** @return array<string, mixed> the PRD §6.1 discount of a redeemed promotion code */
    private function redeem(string $code, GatewayPrice $price, \DateTimeImmutable $now): array
    {
        $promotion = null;
        foreach ($this->all('promotion_code') as $candidate) {
            if ($candidate['code'] === $code && true === $candidate['active']) {
                $promotion = $candidate;
            }
        }
        $coupon = null === $promotion ? null : $this->load('coupon', (string) $promotion['coupon']);
        $expired = null !== $promotion && null !== $promotion['expires_at'] && (int) $promotion['expires_at'] < $now->getTimestamp();
        $exhausted = null !== $promotion && null !== $promotion['max_redemptions'] && (int) $promotion['times_redeemed'] >= (int) $promotion['max_redemptions'];
        $products = null === $coupon ? [] : (array) $coupon['product_ids'];
        $appliesToPrice = [] === $products || \in_array($price->productId, $products, true);
        if (null === $promotion || null === $coupon || true !== $coupon['valid'] || $expired || $exhausted || !$appliesToPrice) {
            throw GatewayUnavailable::because('This promotion code is not valid.');
        }
        ++$promotion['times_redeemed'];
        $this->save('promotion_code', (string) $promotion['id'], $promotion);

        $endsAt = null;
        if ('repeating' === $coupon['duration'] && null !== $coupon['duration_in_months']) {
            $endsAt = Iso::datetime($now->modify('+'.(int) $coupon['duration_in_months'].' months'));
        } elseif ('once' === $coupon['duration']) {
            $endsAt = Iso::datetime(self::addInterval($now, $price->interval ?? 'month'));
        }

        return [
            'coupon_id' => $coupon['id'],
            'promotion_code' => $promotion['code'],
            'percent_off' => $coupon['percent_off'],
            'amount_off' => $coupon['amount_off'],
            'currency' => $coupon['currency'],
            'duration' => $coupon['duration'],
            'ends_at' => $endsAt,
        ];
    }

    /** @param array<string, mixed>|null $discount */
    private static function discounted(int $amount, ?array $discount): int
    {
        if (null === $discount) {
            return $amount;
        }
        if (null !== ($discount['percent_off'] ?? null)) {
            return (int) round($amount * (1 - ((float) $discount['percent_off']) / 100));
        }

        return max(0, $amount - (int) ($discount['amount_off'] ?? 0));
    }

    /** @param array<string, mixed> $subscription */
    private function emitInvoice(string $type, array $subscription, string $billingReason, int $amount): void
    {
        $price = $this->price((string) $subscription['price_id']);
        $this->emit($type, [
            'id' => 'in_fake_'.Ids::hex(14),
            'object' => 'invoice',
            'customer' => $subscription['customer'],
            'billing_reason' => $billingReason,
            'amount_paid' => GatewayEvent::INVOICE_PAID === $type ? $amount : 0,
            'amount_due' => $amount,
            'currency' => $price->currency ?? 'usd',
            'parent' => [
                'type' => 'subscription_details',
                'subscription_details' => ['subscription' => $subscription['id'], 'metadata' => $subscription['metadata']],
            ],
        ]);
    }

    /** @param array<string, mixed> $object */
    private function emit(string $type, array $object): void
    {
        $eventId = 'evt_fake_'.Ids::hex(20);
        $this->db->insert('fake_gateway_event', [
            'event_id' => $eventId,
            'type' => $type,
            'payload' => $this->eventPayload($type, $object, $eventId),
            'created_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function load(string $kind, string $id): ?array
    {
        $json = $this->db->fetchOne('SELECT data FROM fake_gateway_object WHERE id = ? AND kind = ?', [$id, $kind]);
        if (!\is_string($json)) {
            return null;
        }
        $data = json_decode($json, true);

        return \is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $data */
    private function save(string $kind, string $id, array $data): void
    {
        $json = (string) json_encode($data, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        $updated = $this->db->executeStatement('UPDATE fake_gateway_object SET data = ? WHERE id = ? AND kind = ?', [$json, $id, $kind]);
        if (0 === $updated && null === $this->load($kind, $id)) {
            $this->db->insert('fake_gateway_object', ['id' => $id, 'kind' => $kind, 'data' => $json, 'created_at' => $this->clock->now()->format('Y-m-d H:i:s')]);
        }
    }

    /** @return list<array<string, mixed>> newest first */
    private function all(string $kind): array
    {
        $rows = $this->db->fetchFirstColumn('SELECT data FROM fake_gateway_object WHERE kind = ? ORDER BY created_at DESC, id DESC', [$kind]);
        $out = [];
        foreach ($rows as $json) {
            $data = json_decode((string) $json, true);
            if (\is_array($data)) {
                $out[] = $data;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $data */
    private static function toSubscription(array $data): GatewaySubscription
    {
        $at = static fn (mixed $ts): ?\DateTimeImmutable => null === $ts ? null : (new \DateTimeImmutable('@'.(int) $ts))->setTimezone(new \DateTimeZone('UTC'));
        $schedule = \is_array($data['schedule'] ?? null) ? $data['schedule'] : null;

        return new GatewaySubscription(
            (string) $data['id'],
            (string) $data['customer'],
            (string) $data['status'],
            (string) $data['price_id'],
            (string) $data['interval'],
            $at($data['current_period_start']) ?? new \DateTimeImmutable('@0'),
            $at($data['current_period_end']) ?? new \DateTimeImmutable('@0'),
            (bool) $data['cancel_at_period_end'],
            $at($data['trial_end'] ?? null),
            null === $schedule ? null : (string) $schedule['id'],
            null === $schedule ? null : (string) $schedule['price_id'],
            null === $schedule ? null : (string) $schedule['interval'],
            array_map('strval', (array) ($data['metadata'] ?? [])),
            \is_array($data['discount'] ?? null) ? $data['discount'] : null,
        );
    }

    /** @param array<string, mixed> $data */
    private static function toCoupon(array $data): GatewayCoupon
    {
        return new GatewayCoupon(
            (string) $data['id'],
            (string) $data['name'],
            null === $data['percent_off'] ? null : (float) $data['percent_off'],
            null === $data['amount_off'] ? null : (int) $data['amount_off'],
            null === $data['currency'] ? null : (string) $data['currency'],
            (string) $data['duration'],
            null === $data['duration_in_months'] ? null : (int) $data['duration_in_months'],
            array_values(array_map('strval', (array) $data['product_ids'])),
            (bool) $data['valid'],
        );
    }

    /** @param array<string, mixed> $data */
    private function toPromotionCode(array $data): GatewayPromotionCode
    {
        $coupon = $this->load('coupon', (string) ($data['coupon'] ?? '')) ?? [
            'id' => (string) ($data['coupon'] ?? ''), 'name' => '', 'percent_off' => null, 'amount_off' => null, 'currency' => null,
            'duration' => 'once', 'duration_in_months' => null, 'product_ids' => [], 'valid' => false,
        ];

        return new GatewayPromotionCode(
            (string) $data['id'],
            (string) $data['code'],
            (bool) $data['active'],
            self::toCoupon($coupon),
            null === $data['expires_at'] ? null : (new \DateTimeImmutable('@'.(int) $data['expires_at']))->setTimezone(new \DateTimeZone('UTC')),
            null === $data['max_redemptions'] ? null : (int) $data['max_redemptions'],
            (int) $data['times_redeemed'],
            array_map('strval', (array) $data['metadata']),
            (new \DateTimeImmutable('@'.(int) $data['created']))->setTimezone(new \DateTimeZone('UTC')),
        );
    }

    /** One billing interval later, with the day clamped to the month's length (like the gateway). */
    private static function addInterval(\DateTimeImmutable $at, string $interval): \DateTimeImmutable
    {
        $date = Iso::addMonths($at->format('Y-m-d'), 'year' === $interval ? 12 : 1);

        return new \DateTimeImmutable($date.' '.$at->format('H:i:s'), new \DateTimeZone('UTC'));
    }
}
