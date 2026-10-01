<?php

namespace App\Billing\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * POST /checkout/plan-change (PRD §8.3): {type: "changed", plan_id, billing_interval, change, effective_at}, or
 * {type: "checkout", plan_id, billing_interval, checkout_url} when the account has no subscription. The fields of the
 * other type are null.
 */
final class PlanChangeOutput
{
    public function __construct(
        #[OA\Property(enum: ['changed', 'checkout'])]
        public readonly string $type,
        public readonly string $plan_id,
        #[OA\Property(enum: ['month', 'year'])]
        public readonly string $billing_interval,
        #[OA\Property(enum: ['upgrade', 'downgrade'], nullable: true)]
        public readonly ?string $change = null,
        public readonly ?string $effective_at = null,
        public readonly ?string $checkout_url = null,
    ) {
    }

    /** @param array<string, mixed> $result */
    public static function of(array $result): self
    {
        return new self(
            (string) $result['type'],
            (string) $result['plan_id'],
            (string) $result['billing_interval'],
            isset($result['change']) ? (string) $result['change'] : null,
            isset($result['effective_at']) ? (string) $result['effective_at'] : null,
            isset($result['checkout_url']) ? (string) $result['checkout_url'] : null,
        );
    }
}
