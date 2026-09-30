<?php

namespace App\Billing\Application\Query;

use App\Billing\Domain\Model\Feature;
use App\Billing\Domain\PlanRules;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Billing\Domain\Repository\FeatureRepository;
use App\Billing\Domain\Repository\PlanRepository;
use App\Billing\Domain\Repository\UsageCounterRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;

/**
 * An account's plan, usage and one verdict per feature of the catalog (PRD §8.3 GET /customer/usage, and the
 * admin's GET /admin/customers/{id}/usage).
 */
final class UsageQueries
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PlanRepository $plans,
        private readonly FeatureRepository $features,
        private readonly UsageCounterRepository $counters,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return array{
     *     customer_plan: array<string, mixed>|null,
     *     plan: array<string, mixed>|null,
     *     usage: array{questionnaires_used: int, from_at: string, to_at: string, features: array<string, int>}|null,
     *     plan_active: bool,
     *     features: array<string, array{allowed: bool, reason: string|null, limit: int|null, used: int}>,
     * }
     */
    public function forAccount(string $customerId): array
    {
        $customerPlan = $this->customerPlans->find($customerId);
        $plan = null === $customerPlan ? null : $this->plans->find($customerPlan->planId());
        $today = $this->clock->today();
        $usage = null === $customerPlan ? [] : $this->counters->usage($customerId, $customerPlan->fromAt());

        $verdicts = [];
        $slugs = array_map(static fn (Feature $f): string => $f->id(), $this->features->all());
        foreach (array_unique([...$slugs, Feature::RESPONSES]) as $slug) {
            $rejection = PlanRules::capacity($customerPlan, $plan, $today, $slug, $usage);
            $verdicts[$slug] = [
                'allowed' => null === $rejection,
                'reason' => $rejection?->reason,
                'limit' => Feature::RESPONSES === $slug ? $plan?->maxResponses() : $plan?->limitOf($slug),
                'used' => $usage[$slug] ?? 0,
            ];
        }

        $questionnairesUsed = 0;
        foreach (Feature::QUESTIONNAIRE_FEATURES as $counted) {
            $questionnairesUsed += $usage[$counted] ?? 0;
        }

        return [
            'customer_plan' => null === $customerPlan ? null : [
                'plan_id' => $customerPlan->planId(),
                'from_at' => $customerPlan->fromAt(),
                'to_at' => $customerPlan->toAt(),
                'billing_interval' => $customerPlan->billingInterval(),
                'stripe_customer_id' => $customerPlan->stripeCustomerId(),
                'stripe_subscription_id' => $customerPlan->stripeSubscriptionId(),
                'trial_end' => Iso::datetime($customerPlan->trialEnd()),
                'discount' => $customerPlan->discount(),
                'created_at' => Iso::datetime($customerPlan->createdAt()),
                'updated_at' => Iso::datetime($customerPlan->updatedAt()),
            ],
            'plan' => null === $plan ? null : [
                'id' => $plan->id(),
                'plan_name' => $plan->planName(),
                'plan_description' => $plan->planDescription(),
                'max_questionnaires' => $plan->maxQuestionnaires(),
                'max_responses' => $plan->maxResponses(),
                'price_amount' => $plan->priceAmount(),
                'currency' => $plan->currency(),
                'yearly_price_amount' => $plan->yearlyPriceAmount(),
                'trial_days' => $plan->trialDays(),
            ],
            'usage' => null === $customerPlan ? null : [
                'questionnaires_used' => $questionnairesUsed,
                'from_at' => $customerPlan->fromAt(),
                'to_at' => $customerPlan->toAt(),
                'features' => $usage,
            ],
            'plan_active' => null !== $customerPlan && null !== $plan && $customerPlan->isActiveOn($today),
            'features' => $verdicts,
        ];
    }
}
