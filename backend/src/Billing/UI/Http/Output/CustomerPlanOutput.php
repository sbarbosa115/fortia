<?php

declare(strict_types=1);

namespace App\Billing\UI\Http\Output;

use OpenApi\Attributes as OA;

/** An account's plan (PRD §6.1 CustomerPlan). */
final class CustomerPlanOutput
{
    /**
     * @param array<string, mixed>|null $discount
     */
    public function __construct(
        public readonly string $plan_id,
        public readonly string $from_at,
        public readonly string $to_at,
        #[OA\Property(enum: ['month', 'year'])]
        public readonly string $billing_interval,
        public readonly ?string $stripe_customer_id,
        public readonly ?string $stripe_subscription_id,
        public readonly ?string $trial_end,
        #[OA\Property(type: 'object', nullable: true, additionalProperties: true)]
        public readonly ?array $discount,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function of(array $row): self
    {
        return new self(
            (string) $row['plan_id'],
            (string) $row['from_at'],
            (string) $row['to_at'],
            (string) $row['billing_interval'],
            isset($row['stripe_customer_id']) ? (string) $row['stripe_customer_id'] : null,
            isset($row['stripe_subscription_id']) ? (string) $row['stripe_subscription_id'] : null,
            isset($row['trial_end']) ? (string) $row['trial_end'] : null,
            \is_array($row['discount'] ?? null) ? $row['discount'] : null,
            isset($row['created_at']) ? (string) $row['created_at'] : null,
            isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        );
    }
}
