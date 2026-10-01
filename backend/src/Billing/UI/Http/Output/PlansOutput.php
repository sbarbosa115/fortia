<?php

namespace App\Billing\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /plans (PRD §8.3). */
final class PlansOutput
{
    /**
     * @param list<CatalogPlanOutput> $plans
     */
    public function __construct(
        public readonly ?string $current_plan_id,
        #[OA\Property(enum: ['month', 'year'], nullable: true)]
        public readonly ?string $current_billing_interval,
        /** The last day of the plan window (YYYY-MM-DD). */
        public readonly ?string $active_until,
        public readonly ?string $scheduled_plan_id,
        #[OA\Property(enum: ['month', 'year'], nullable: true)]
        public readonly ?string $scheduled_billing_interval,
        public readonly bool $cancel_at_period_end,
        public readonly bool $trial_eligible,
        public readonly ?string $trial_end,
        public readonly ?DiscountOutput $discount,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: CatalogPlanOutput::class)))]
        public readonly array $plans,
    ) {
    }

    /** @param array<string, mixed> $data PlanQueries::forAccount() */
    public static function of(array $data): self
    {
        return new self(
            $data['current_plan_id'],
            $data['current_billing_interval'],
            $data['active_until'],
            $data['scheduled_plan_id'],
            $data['scheduled_billing_interval'],
            (bool) $data['cancel_at_period_end'],
            (bool) $data['trial_eligible'],
            $data['trial_end'],
            DiscountOutput::ofNullable($data['discount']),
            array_map(static fn (array $plan): CatalogPlanOutput => CatalogPlanOutput::of($plan), $data['plans']),
        );
    }
}
