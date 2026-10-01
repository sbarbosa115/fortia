<?php

namespace App\Billing\Application\Port;

/**
 * A coupon to create in the gateway (PRD §7.4 Coupons, §8.13). The gateway only restricts coupons by product, so
 * $productIds is how plans and billing intervals are limited; empty = every product.
 */
final class CouponRequest
{
    /**
     * @param list<string> $productIds
     */
    public function __construct(
        public readonly string $name,
        public readonly ?float $percentOff,
        public readonly ?int $amountOff,
        public readonly ?string $currency,
        /** once | repeating | forever */
        public readonly string $duration,
        public readonly ?int $durationInMonths = null,
        public readonly array $productIds = [],
    ) {
    }
}
