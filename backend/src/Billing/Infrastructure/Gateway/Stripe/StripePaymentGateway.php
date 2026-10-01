<?php

namespace App\Billing\Infrastructure\Gateway\Stripe;

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
use App\Billing\Infrastructure\Gateway\StripeEventReader;
use App\Shared\Domain\Iso;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stripe (PAYMENT_PROVIDER=stripe): Checkout, Billing Portal, Subscription Schedules, Coupons and Promotion Codes,
 * signed webhooks (PRD §13.2, §16.3 #12). Written against stripe/stripe-php's current API; it has not been run
 * against a live account in this repo (no keys in dev), see the README's known gaps.
 */
final class StripePaymentGateway implements PaymentGateway
{
    private ?StripeClient $client = null;

    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $secretKey,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        private readonly string $webhookSecret,
    ) {
    }

    public function createCheckoutSession(CheckoutRequest $request): string
    {
        $subscriptionData = ['metadata' => $request->metadata()];
        if ($request->trialDays > 0) {
            $subscriptionData['trial_period_days'] = $request->trialDays;
            // A card is always required; without one when the trial ends, the subscription is canceled (PRD §7.3).
            $subscriptionData['trial_settings'] = ['end_behavior' => ['missing_payment_method' => 'cancel']];
        }
        $params = [
            'mode' => 'subscription',
            'line_items' => [['price' => $request->priceId, 'quantity' => 1]],
            'metadata' => $request->metadata(),
            'subscription_data' => $subscriptionData,
            'allow_promotion_codes' => true,
            'payment_method_collection' => 'always',
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
        ];
        if (null !== $request->gatewayCustomerId) {
            $params['customer'] = $request->gatewayCustomerId;
        } elseif (null !== $request->customerEmail) {
            $params['customer_email'] = $request->customerEmail;
        }

        return $this->call(static function (StripeClient $stripe) use ($params): string {
            $session = $stripe->checkout->sessions->create($params);

            return (string) $session->url;
        });
    }

    public function subscription(string $subscriptionId): ?GatewaySubscription
    {
        try {
            return $this->call(fn (StripeClient $stripe): GatewaySubscription => $this->read($stripe, $subscriptionId));
        } catch (GatewayUnavailable $e) {
            if ($e->getPrevious() instanceof InvalidRequestException && 404 === $e->getPrevious()->getHttpStatus()) {
                return null;
            }
            throw $e;
        }
    }

    public function changePrice(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription
    {
        return $this->call(function (StripeClient $stripe) use ($subscriptionId, $priceId, $metadata): GatewaySubscription {
            $this->release($stripe, $subscriptionId);
            $subscription = $stripe->subscriptions->retrieve($subscriptionId);
            $stripe->subscriptions->update($subscriptionId, [
                'items' => [['id' => $subscription->items->data[0]->id, 'price' => $priceId]],
                'proration_behavior' => 'always_invoice',
                // The prorated difference is charged now; if that charge fails, the change fails (PRD §7.4).
                'payment_behavior' => 'error_if_incomplete',
                'metadata' => $metadata,
            ]);

            return $this->read($stripe, $subscriptionId);
        });
    }

    public function scheduleChange(string $subscriptionId, string $priceId, array $metadata): GatewaySubscription
    {
        return $this->call(function (StripeClient $stripe) use ($subscriptionId, $priceId, $metadata): GatewaySubscription {
            $subscription = $stripe->subscriptions->retrieve($subscriptionId);
            $scheduleId = self::id($subscription->schedule);
            $schedule = null === $scheduleId
                ? $stripe->subscriptionSchedules->create(['from_subscription' => $subscriptionId])
                : $stripe->subscriptionSchedules->retrieve($scheduleId);
            $item = $subscription->items->data[0];
            $current = $schedule->phases[0];
            $stripe->subscriptionSchedules->update($schedule->id, [
                'end_behavior' => 'release',
                'phases' => [
                    [
                        'items' => [['price' => self::id($item->price), 'quantity' => (int) ($item->quantity ?? 1)]],
                        'start_date' => $current->start_date,
                        'end_date' => $item->current_period_end,
                        'metadata' => self::strings($subscription->metadata->toArray()),
                    ],
                    // The cheaper plan for one cycle; then the schedule is released and it renews on it.
                    ['items' => [['price' => $priceId, 'quantity' => 1]], 'duration' => ['interval' => self::intervalOfPrice($stripe, $priceId), 'interval_count' => 1], 'metadata' => $metadata],
                ],
            ]);

            return $this->read($stripe, $subscriptionId);
        });
    }

    public function releaseSchedule(string $subscriptionId): GatewaySubscription
    {
        return $this->call(function (StripeClient $stripe) use ($subscriptionId): GatewaySubscription {
            $this->release($stripe, $subscriptionId);

            return $this->read($stripe, $subscriptionId);
        });
    }

    public function cancelAtPeriodEnd(string $subscriptionId): GatewaySubscription
    {
        return $this->call(function (StripeClient $stripe) use ($subscriptionId): GatewaySubscription {
            $this->release($stripe, $subscriptionId);
            $stripe->subscriptions->update($subscriptionId, ['cancel_at_period_end' => true]);

            return $this->read($stripe, $subscriptionId);
        });
    }

    public function resume(string $subscriptionId): GatewaySubscription
    {
        return $this->call(function (StripeClient $stripe) use ($subscriptionId): GatewaySubscription {
            $stripe->subscriptions->update($subscriptionId, ['cancel_at_period_end' => false]);

            return $this->read($stripe, $subscriptionId);
        });
    }

    public function createPortalSession(string $gatewayCustomerId, string $returnUrl): string
    {
        return $this->call(static fn (StripeClient $stripe): string => (string) $stripe->billingPortal->sessions->create([
            'customer' => $gatewayCustomerId,
            'return_url' => $returnUrl,
        ])->url);
    }

    public function price(string $priceId): ?GatewayPrice
    {
        try {
            return $this->call(static function (StripeClient $stripe) use ($priceId): GatewayPrice {
                $price = $stripe->prices->retrieve($priceId);

                return new GatewayPrice(
                    $price->id,
                    null === $price->unit_amount ? null : (int) $price->unit_amount,
                    (string) $price->currency,
                    null === $price->recurring ? null : (string) $price->recurring->interval,
                    (bool) $price->active,
                    self::id($price->product),
                );
            });
        } catch (GatewayUnavailable $e) {
            if ($e->getPrevious() instanceof InvalidRequestException && 404 === $e->getPrevious()->getHttpStatus()) {
                return null;
            }
            throw $e;
        }
    }

    public function createCoupon(CouponRequest $request): GatewayCoupon
    {
        $params = ['name' => $request->name, 'duration' => $request->duration];
        if (null !== $request->percentOff) {
            $params['percent_off'] = $request->percentOff;
        } else {
            $params['amount_off'] = $request->amountOff;
            $params['currency'] = $request->currency;
        }
        if ('repeating' === $request->duration) {
            $params['duration_in_months'] = $request->durationInMonths;
        }
        if ([] !== $request->productIds) {
            $params['applies_to'] = ['products' => $request->productIds];
        }

        return $this->call(static fn (StripeClient $stripe): GatewayCoupon => self::coupon($stripe->coupons->create($params + ['expand' => ['applies_to']])));
    }

    public function deleteCoupon(string $couponId): void
    {
        $this->call(static fn (StripeClient $stripe) => $stripe->coupons->delete($couponId));
    }

    public function createPromotionCode(PromotionCodeRequest $request): GatewayPromotionCode
    {
        $params = [
            'promotion' => ['type' => 'coupon', 'coupon' => $request->couponId],
            'code' => $request->code,
            'metadata' => $request->metadata,
        ];
        if (null !== $request->expiresAt) {
            $params['expires_at'] = $request->expiresAt->getTimestamp();
        }
        if (null !== $request->maxRedemptions) {
            $params['max_redemptions'] = $request->maxRedemptions;
        }

        return $this->call(static fn (StripeClient $stripe): GatewayPromotionCode => self::promotionCode($stripe->promotionCodes->create($params + ['expand' => ['promotion.coupon', 'promotion.coupon.applies_to']])));
    }

    public function deactivatePromotionCode(string $promotionCodeId): ?GatewayPromotionCode
    {
        try {
            return $this->call(static fn (StripeClient $stripe): GatewayPromotionCode => self::promotionCode($stripe->promotionCodes->update($promotionCodeId, [
                'active' => false,
                'expand' => ['promotion.coupon', 'promotion.coupon.applies_to'],
            ])));
        } catch (GatewayUnavailable $e) {
            if ($e->getPrevious() instanceof InvalidRequestException && 404 === $e->getPrevious()->getHttpStatus()) {
                return null;
            }
            throw $e;
        }
    }

    public function promotionCodes(): array
    {
        return $this->call(static function (StripeClient $stripe): array {
            $codes = [];
            foreach ($stripe->promotionCodes->all(['limit' => 100, 'expand' => ['data.promotion.coupon', 'data.promotion.coupon.applies_to']])->autoPagingIterator() as $code) {
                $codes[] = self::promotionCode($code);
            }

            return $codes;
        });
    }

    public function promotionCodeByCode(string $code): ?GatewayPromotionCode
    {
        return $this->call(static function (StripeClient $stripe) use ($code): ?GatewayPromotionCode {
            $found = $stripe->promotionCodes->all(['code' => $code, 'limit' => 10, 'expand' => ['data.promotion.coupon', 'data.promotion.coupon.applies_to']])->data;
            usort($found, static fn ($a, $b): int => (int) $b->active <=> (int) $a->active);

            return [] === $found ? null : self::promotionCode($found[0]);
        });
    }

    public function parseWebhook(string $payload, string $signatureHeader): GatewayEvent
    {
        try {
            $event = Webhook::constructEvent($payload, $signatureHeader, $this->webhookSecret);
        } catch (SignatureVerificationException|\UnexpectedValueException $e) {
            throw new InvalidWebhookSignature($e->getMessage(), 0, $e);
        }

        return StripeEventReader::read($event->toArray());
    }

    // ── Internals ────────────────────────────────────────────────────────────────────────────────────────────────

    /**
     * @template T
     *
     * @param callable(StripeClient): T $call
     *
     * @return T
     */
    private function call(callable $call): mixed
    {
        if ('' === $this->secretKey) {
            throw GatewayUnavailable::because('The payment gateway is not configured (STRIPE_SECRET_KEY).');
        }
        $this->client ??= new StripeClient($this->secretKey);
        try {
            return $call($this->client);
        } catch (ApiErrorException $e) {
            throw GatewayUnavailable::because($e->getMessage(), $e);
        }
    }

    private function release(StripeClient $stripe, string $subscriptionId): void
    {
        $scheduleId = self::id($stripe->subscriptions->retrieve($subscriptionId)->schedule);
        if (null !== $scheduleId) {
            $schedule = $stripe->subscriptionSchedules->retrieve($scheduleId);
            if (\in_array($schedule->status, ['not_started', 'active'], true)) {
                $stripe->subscriptionSchedules->release($scheduleId);
            }
        }
    }

    private function read(StripeClient $stripe, string $subscriptionId): GatewaySubscription
    {
        $subscription = $stripe->subscriptions->retrieve($subscriptionId, ['expand' => ['schedule', 'discounts', 'discounts.promotion_code']]);
        $item = $subscription->items->data[0];
        $price = $item->price;
        $scheduledPriceId = null;
        $scheduledInterval = null;
        $schedule = $subscription->schedule;
        if (\is_object($schedule) && \in_array($schedule->status, ['not_started', 'active'], true)) {
            $now = time();
            foreach ($schedule->phases as $phase) {
                if ($phase->start_date > $now && null !== ($phase->items[0] ?? null)) {
                    $scheduledPriceId = self::id($phase->items[0]->price);
                    $scheduledInterval = null === $scheduledPriceId ? null : self::intervalOfPrice($stripe, $scheduledPriceId);
                    break;
                }
            }
        }

        return new GatewaySubscription(
            $subscription->id,
            (string) self::id($subscription->customer),
            (string) $subscription->status,
            (string) $price->id,
            (string) ($price->recurring->interval ?? 'month'),
            new \DateTimeImmutable('@'.(int) $item->current_period_start),
            new \DateTimeImmutable('@'.(int) $item->current_period_end),
            (bool) $subscription->cancel_at_period_end,
            null === $subscription->trial_end ? null : new \DateTimeImmutable('@'.(int) $subscription->trial_end),
            self::id($schedule),
            $scheduledPriceId,
            $scheduledInterval,
            self::strings($subscription->metadata->toArray()),
            self::discount($subscription->discounts ?? []),
        );
    }

    /**
     * The PRD §6.1 discount of the subscription's first discount.
     *
     * @param array<int, mixed> $discounts
     *
     * @return array<string, mixed>|null
     */
    private static function discount(array $discounts): ?array
    {
        $discount = $discounts[0] ?? null;
        if (!\is_object($discount)) {
            return null;
        }
        $coupon = $discount->source->coupon ?? $discount->coupon ?? null;
        if (\is_string($coupon) || null === $coupon) {
            return null === $coupon ? null : ['coupon_id' => $coupon, 'promotion_code' => null, 'percent_off' => null, 'amount_off' => null, 'currency' => null, 'duration' => 'once', 'ends_at' => null];
        }
        $promotion = $discount->promotion_code ?? null;

        return [
            'coupon_id' => $coupon->id,
            'promotion_code' => \is_object($promotion) ? $promotion->code : null,
            'percent_off' => $coupon->percent_off,
            'amount_off' => $coupon->amount_off,
            'currency' => $coupon->currency,
            'duration' => $coupon->duration,
            'ends_at' => null === ($discount->end ?? null) ? null : Iso::datetime(new \DateTimeImmutable('@'.(int) $discount->end)),
        ];
    }

    private static function intervalOfPrice(StripeClient $stripe, string $priceId): string
    {
        return (string) ($stripe->prices->retrieve($priceId)->recurring->interval ?? 'month');
    }

    private static function coupon(object $coupon): GatewayCoupon
    {
        return new GatewayCoupon(
            (string) $coupon->id,
            (string) ($coupon->name ?? ''),
            null === $coupon->percent_off ? null : (float) $coupon->percent_off,
            null === $coupon->amount_off ? null : (int) $coupon->amount_off,
            null === $coupon->currency ? null : (string) $coupon->currency,
            (string) $coupon->duration,
            null === $coupon->duration_in_months ? null : (int) $coupon->duration_in_months,
            array_values(array_map('strval', (array) ($coupon->applies_to->products ?? []))),
            (bool) $coupon->valid,
        );
    }

    private static function promotionCode(object $code): GatewayPromotionCode
    {
        $coupon = $code->promotion->coupon ?? $code->coupon ?? null;

        return new GatewayPromotionCode(
            (string) $code->id,
            (string) $code->code,
            (bool) $code->active,
            \is_object($coupon)
                ? self::coupon($coupon)
                : new GatewayCoupon((string) $coupon, '', null, null, null, 'once', null, [], false),
            null === $code->expires_at ? null : new \DateTimeImmutable('@'.(int) $code->expires_at),
            null === $code->max_redemptions ? null : (int) $code->max_redemptions,
            (int) $code->times_redeemed,
            self::strings($code->metadata?->toArray() ?? []),
            new \DateTimeImmutable('@'.(int) $code->created),
        );
    }

    private static function id(mixed $value): ?string
    {
        if (\is_object($value)) {
            $value = $value->id ?? null;
        }

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<string, string>
     */
    private static function strings(array $values): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            if (\is_scalar($value)) {
                $out[(string) $key] = (string) $value;
            }
        }

        return $out;
    }
}
