<?php

namespace App\Billing\Application;

use App\Billing\Domain\Error\PlanNotFound;
use App\Billing\Domain\Error\PlanNotPurchasable;
use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\PlanRepository;

/** The plan catalog as billing reads it: a plan to buy and its gateway price, or the plan a gateway price belongs to. */
final class PlanCatalog
{
    public function __construct(private readonly PlanRepository $plans)
    {
    }

    /**
     * The plan and its gateway price for a billing interval (404 PLAN_NOT_FOUND, 400 PLAN_NOT_PURCHASABLE).
     *
     * @return array{0: Plan, 1: string}
     */
    public function purchasable(string $planId, string $interval): array
    {
        $plan = $this->plans->find($planId) ?? throw new PlanNotFound();
        $priceId = 'year' === $interval
            ? ($plan->isYearlyPurchasable() ? $plan->stripeYearlyPriceId() : null)
            : ($plan->isPurchasable() ? $plan->stripePriceId() : null);

        return [$plan, $priceId ?? throw new PlanNotPurchasable()];
    }

    /** The plan whose monthly or yearly gateway price this is. */
    public function byPriceId(?string $priceId): ?Plan
    {
        if (null === $priceId) {
            return null;
        }
        foreach ($this->plans->all() as $plan) {
            if ($priceId === $plan->stripePriceId() || $priceId === $plan->stripeYearlyPriceId()) {
                return $plan;
            }
        }

        return null;
    }

    public function find(?string $planId): ?Plan
    {
        return null === $planId ? null : $this->plans->find($planId);
    }
}
