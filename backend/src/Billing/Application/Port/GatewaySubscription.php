<?php

namespace App\Billing\Application\Port;

/** A subscription as the gateway sees it (the source of truth for what is charged today, PRD §7.4). */
final class GatewaySubscription
{
    /**
     * @param array<string, string>     $metadata {customer_id, plan_id, billing_interval}
     * @param array<string, mixed>|null $discount PRD §6.1 {coupon_id, promotion_code?, percent_off?, amount_off?,
     *                                            currency?, duration, ends_at?}
     */
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        /** trialing, active, past_due, unpaid, canceled, incomplete, incomplete_expired, paused */
        public readonly string $status,
        public readonly string $priceId,
        public readonly string $interval,
        public readonly \DateTimeImmutable $currentPeriodStart,
        public readonly \DateTimeImmutable $currentPeriodEnd,
        public readonly bool $cancelAtPeriodEnd,
        public readonly ?\DateTimeImmutable $trialEnd,
        public readonly ?string $scheduleId,
        /** The price of the next phase of a pending schedule (a downgrade), if any. */
        public readonly ?string $scheduledPriceId,
        public readonly ?string $scheduledInterval,
        public readonly array $metadata,
        public readonly ?array $discount,
    ) {
    }

    public function isCanceled(): bool
    {
        return \in_array($this->status, ['canceled', 'incomplete_expired'], true);
    }

    public function hasScheduledChange(): bool
    {
        return null !== $this->scheduledPriceId;
    }
}
