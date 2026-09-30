<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Billing\Domain\Repository\UsageCounterRepository;

/**
 * The usage counters of the current plan period (PRD §7.2, §13.8 absorbed): what the plan gate reads.
 * Counting is done by CountUsage from domain events; call record() directly only for usage that has no event.
 */
final class Usage
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly UsageCounterRepository $counters,
    ) {
    }

    /** Adds units of a feature to the account's current period. An account without a plan counts nothing. */
    public function record(string $customerId, string $feature, int $amount = 1): void
    {
        $plan = $this->customerPlans->find($customerId);
        if (null === $plan) {
            return;
        }
        $this->counters->counter($customerId, $plan->fromAt(), $plan->toAt(), $feature)->add($amount);
    }

    /** @return array<string, int> used units per feature in the current period */
    public function current(string $customerId): array
    {
        $plan = $this->customerPlans->find($customerId);

        return null === $plan ? [] : $this->counters->usage($customerId, $plan->fromAt());
    }

    /**
     * Merges counters into the open period (PUT /admin/customers/{id}/usage).
     *
     * @param array<string, int> $features
     */
    public function set(string $customerId, array $features): void
    {
        $plan = $this->customerPlans->find($customerId);
        if (null === $plan) {
            return;
        }
        foreach ($features as $feature => $used) {
            $this->counters->counter($customerId, $plan->fromAt(), $plan->toAt(), $feature)->set($used);
        }
    }

    /** Every counter of the current period back to 0 (an upgrade, PRD §7.4). */
    public function reset(string $customerId): void
    {
        $plan = $this->customerPlans->find($customerId);
        if (null !== $plan) {
            $this->counters->resetPeriod($customerId, $plan->fromAt());
        }
    }
}
