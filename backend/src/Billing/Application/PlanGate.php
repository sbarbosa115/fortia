<?php

namespace App\Billing\Application;

use App\Billing\Domain\Model\Feature;
use App\Billing\Domain\PlanRejection;
use App\Billing\Domain\PlanRules;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Billing\Domain\Repository\PlanRepository;
use App\Billing\Domain\Repository\UsageCounterRepository;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Clock;

/**
 * The plan gate (PRD §7.1), run after authentication and validation and before execution (§5 A2):
 *
 *     $gate->capacity($caller, Feature::USERS);        // the action will count as usage (PRD §7.2)
 *     $gate->feature($caller, Feature::API);            // only "does the plan include it"
 *
 * Rejected with 429 PLAN_LIMIT_REACHED {reason, feature}. An Admin (not assuming) always passes. The counting itself
 * happens when the action's domain event (with its feature()) is handled — see CountUsage.
 */
final class PlanGate
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly PlanRepository $plans,
        private readonly UsageCounterRepository $counters,
        private readonly Clock $clock,
    ) {
    }

    /** @throws PlanLimitReached */
    public function capacity(Caller $caller, string $feature): void
    {
        if (!$caller->isAdmin()) {
            $this->capacityForAccount($caller->customerId, $feature);
        }
    }

    /**
     * For requests without a console user, gated on the owner account's plan (a respondent creating a session).
     *
     * @throws PlanLimitReached
     */
    public function capacityForAccount(string $customerId, string $feature): void
    {
        $rejection = $this->capacityRejection($customerId, $feature);
        if (null !== $rejection) {
            throw PlanLimitReached::because($rejection);
        }
    }

    /** @throws PlanLimitReached */
    public function feature(Caller $caller, string $feature): void
    {
        if (!$caller->isAdmin()) {
            $this->featureForAccount($caller->customerId, $feature);
        }
    }

    /** @throws PlanLimitReached */
    public function featureForAccount(string $customerId, string $feature): void
    {
        $customerPlan = $this->customerPlans->find($customerId);
        $plan = null === $customerPlan ? null : $this->plans->find($customerPlan->planId());
        $rejection = PlanRules::feature($customerPlan, $plan, $this->clock->today(), $feature);
        if (null !== $rejection) {
            throw PlanLimitReached::because($rejection);
        }
    }

    public function capacityRejection(string $customerId, string $feature): ?PlanRejection
    {
        $customerPlan = $this->customerPlans->find($customerId);
        $plan = null === $customerPlan ? null : $this->plans->find($customerPlan->planId());
        $usage = null === $customerPlan ? [] : $this->counters->usage($customerId, $customerPlan->fromAt());

        return PlanRules::capacity($customerPlan, $plan, $this->clock->today(), $feature, $usage);
    }

    /** Whether a feature's gate would pass, without throwing (the console's plan verdicts, the chat's tools). */
    public function allows(string $customerId, string $feature): bool
    {
        return null === $this->capacityRejection($customerId, $feature);
    }

    /** @return list<string> the canonical feature slugs, for callers that list verdicts */
    public static function canonicalFeatures(): array
    {
        return [
            Feature::REGULAR, Feature::DIAGNOSTIC, Feature::QUIZ_FUNNEL, Feature::CHAIN, Feature::CHAT,
            Feature::ORGANIZATIONS, Feature::ASSIGNATIONS, Feature::STYLES, Feature::ANALYTICS, Feature::DASHBOARDS,
            Feature::USERS, Feature::API, Feature::WEBHOOK, Feature::PROFILE, Feature::RESPONSES,
        ];
    }
}
