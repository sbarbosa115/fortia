<?php

namespace App\Billing\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A discount on the subscription (PRD §6.1 CustomerPlan.discount). */
final class DiscountOutput
{
    public function __construct(
        public readonly string $coupon_id,
        public readonly ?string $promotion_code,
        public readonly ?float $percent_off,
        public readonly ?int $amount_off,
        public readonly ?string $currency,
        #[OA\Property(enum: ['once', 'repeating', 'forever'])]
        public readonly string $duration,
        public readonly ?string $ends_at,
    ) {
    }

    /** @param array<string, mixed>|null $discount */
    public static function ofNullable(?array $discount): ?self
    {
        if (null === $discount || !isset($discount['coupon_id'])) {
            return null;
        }

        return new self(
            (string) $discount['coupon_id'],
            isset($discount['promotion_code']) ? (string) $discount['promotion_code'] : null,
            isset($discount['percent_off']) ? (float) $discount['percent_off'] : null,
            isset($discount['amount_off']) ? (int) $discount['amount_off'] : null,
            isset($discount['currency']) ? (string) $discount['currency'] : null,
            (string) ($discount['duration'] ?? 'once'),
            isset($discount['ends_at']) ? (string) $discount['ends_at'] : null,
        );
    }
}
