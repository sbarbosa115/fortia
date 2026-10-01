<?php

namespace App\Billing\Application\Query;

use App\Billing\Application\PlanCatalog;
use App\Billing\Application\Port\GatewayUnavailable;
use App\Billing\Application\Subscriptions;
use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Billing\Domain\Repository\FeatureRepository;
use App\Billing\Domain\Repository\PlanRepository;
use App\Shared\Domain\Iso;

/**
 * GET /plans (PRD §8.3): the catalog as the account can buy it, with its current plan and what is pending. The
 * schedule and cancellation are read live from the gateway; if that fails, "nothing pending" is assumed.
 */
final class PlanQueries
{
    public function __construct(
        private readonly PlanRepository $plans,
        private readonly FeatureRepository $features,
        private readonly CustomerPlanRepository $customerPlans,
        private readonly Subscriptions $subscriptions,
        private readonly PlanCatalog $catalog,
    ) {
    }

    /**
     * @return array{
     *     current_plan_id: string|null, current_billing_interval: string|null, active_until: string|null,
     *     scheduled_plan_id: string|null, scheduled_billing_interval: string|null, cancel_at_period_end: bool,
     *     trial_eligible: bool, trial_end: string|null, discount: array<string, mixed>|null,
     *     plans: list<array<string, mixed>>,
     * }
     */
    public function forAccount(string $customerId): array
    {
        $customerPlan = $this->customerPlans->find($customerId);
        try {
            $subscription = $this->subscriptions->of($customerId);
        } catch (GatewayUnavailable) {
            $subscription = null;
        }

        return [
            'current_plan_id' => $customerPlan?->planId(),
            'current_billing_interval' => $customerPlan?->billingInterval(),
            'active_until' => $customerPlan?->toAt(),
            'scheduled_plan_id' => $this->catalog->byPriceId($subscription?->scheduledPriceId)?->id(),
            'scheduled_billing_interval' => $subscription?->scheduledInterval,
            'cancel_at_period_end' => null !== $subscription && $subscription->cancelAtPeriodEnd,
            // PRD §7.3: the gateway trial only once per account.
            'trial_eligible' => null === $customerPlan?->trialUsedAt(),
            'trial_end' => Iso::datetime($customerPlan?->trialEnd()),
            'discount' => $customerPlan?->discount(),
            'plans' => $this->catalog(),
        ];
    }

    /** @return list<array<string, mixed>> priced plans first (by price), then the rest by name */
    private function catalog(): array
    {
        $names = [];
        foreach ($this->features->all() as $feature) {
            $names[$feature->id()] = $feature->featureName();
        }
        $plans = $this->plans->all();
        usort($plans, static function (Plan $a, Plan $b): int {
            $priced = (null === $a->priceAmount()) <=> (null === $b->priceAmount());
            if (0 !== $priced) {
                return $priced;
            }
            $price = ($a->priceAmount() ?? 0) <=> ($b->priceAmount() ?? 0);

            return 0 !== $price ? $price : strcasecmp($a->planName(), $b->planName());
        });

        return array_map(static fn (Plan $plan): array => [
            'id' => $plan->id(),
            'plan_name' => $plan->planName(),
            'plan_description' => $plan->planDescription(),
            'price_amount' => $plan->priceAmount(),
            'currency' => $plan->currency(),
            'purchasable' => $plan->isPurchasable(),
            'yearly_price_amount' => $plan->yearlyPriceAmount(),
            'yearly_purchasable' => $plan->isYearlyPurchasable(),
            'max_questionnaires' => $plan->maxQuestionnaires(),
            'max_responses' => $plan->maxResponses(),
            'trial_days' => $plan->trialDays(),
            'features' => array_map(static fn (array $feature): array => [
                'feature_id' => $feature['feature_id'],
                'feature_name' => $names[$feature['feature_id']] ?? $feature['feature_id'],
                'limit' => $feature['limit'],
            ], $plan->features()),
        ], $plans);
    }
}
