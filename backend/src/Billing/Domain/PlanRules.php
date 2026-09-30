<?php

namespace App\Billing\Domain;

use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Model\Feature;
use App\Billing\Domain\Model\Plan;

/**
 * The plan gate's decision (PRD §7.1), without I/O: given the account's plan, today and the usage of the period,
 * may it use a feature? Null means yes; a PlanRejection says why not.
 */
final class PlanRules
{
    /**
     * A capacity gate: the limit against the usage counters (the action will count).
     *
     * @param array<string, int> $usage used units per feature in the current period
     */
    public static function capacity(?CustomerPlan $customerPlan, ?Plan $plan, string $today, string $feature, array $usage): ?PlanRejection
    {
        $inapplicable = self::applicable($customerPlan, $plan, $today, $feature);
        if (null !== $inapplicable || null === $plan) {
            return $inapplicable;
        }

        if (Feature::RESPONSES === $feature) {
            return self::exceeds($plan->maxResponses(), $usage[Feature::RESPONSES] ?? 0)
                ? new PlanRejection('RESPONSE_LIMIT_REACHED', $feature)
                : null;
        }

        $limit = $plan->limitOf($feature);
        if (null === $limit || 0 === $limit) {
            return new PlanRejection('FEATURE_NOT_IN_PLAN', $feature, $limit);
        }
        if ($limit > 0 && ($usage[$feature] ?? 0) >= $limit) {
            return new PlanRejection('FEATURE_LIMIT_REACHED', $feature, $limit);
        }

        if (\in_array($feature, Feature::QUESTIONNAIRE_FEATURES, true)) {
            $questionnaires = 0;
            foreach (Feature::QUESTIONNAIRE_FEATURES as $counted) {
                $questionnaires += $usage[$counted] ?? 0;
            }
            if (self::exceeds($plan->maxQuestionnaires(), $questionnaires)) {
                return new PlanRejection('QUESTIONNAIRE_LIMIT_REACHED', $feature, $plan->maxQuestionnaires());
            }
        }

        return null;
    }

    /** A feature gate: only that the plan (active) includes the feature, with a limit present and ≠ 0. */
    public static function feature(?CustomerPlan $customerPlan, ?Plan $plan, string $today, string $feature): ?PlanRejection
    {
        $inapplicable = self::applicable($customerPlan, $plan, $today, $feature);
        if (null !== $inapplicable || null === $plan) {
            return $inapplicable;
        }
        if (Feature::RESPONSES === $feature) {
            return null;
        }
        $limit = $plan->limitOf($feature);

        return null === $limit || 0 === $limit ? new PlanRejection('FEATURE_NOT_IN_PLAN', $feature, $limit) : null;
    }

    private static function applicable(?CustomerPlan $customerPlan, ?Plan $plan, string $today, string $feature): ?PlanRejection
    {
        if (null === $customerPlan) {
            return new PlanRejection('NO_PLAN', $feature);
        }
        if (!$customerPlan->isActiveOn($today)) {
            return new PlanRejection('PLAN_INACTIVE', $feature);
        }
        if (null === $plan) {
            return new PlanRejection('PLAN_NOT_FOUND', $feature);
        }

        return null;
    }

    /** A cap: null = no cap, negative = unlimited, otherwise reached when used >= cap. */
    private static function exceeds(?int $cap, int $used): bool
    {
        return null !== $cap && $cap >= 0 && $used >= $cap;
    }
}
