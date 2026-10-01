<?php

namespace App\Billing\Application\Port;

/** The customer-facing code of a coupon (PRD §8.13: code, expires_at, max_redemptions). */
final class PromotionCodeRequest
{
    /**
     * @param array<string, string> $metadata e.g. {plan_ids, billing_intervals} as the admin sent them
     */
    public function __construct(
        public readonly string $couponId,
        public readonly string $code,
        public readonly ?\DateTimeImmutable $expiresAt = null,
        public readonly ?int $maxRedemptions = null,
        public readonly array $metadata = [],
    ) {
    }
}
