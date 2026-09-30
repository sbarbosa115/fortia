<?php

namespace App\Billing\Infrastructure\Seed;

use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Shared\Application\Seed\DemoAccounts;
use App\Shared\Application\Seed\DemoSeeder;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;

/** Each demo account's plan, valid from today for one calendar month (like a sign-up, PRD §7.3). */
final class DemoPlansSeeder implements DemoSeeder
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly Clock $clock,
    ) {
    }

    public static function priority(): int
    {
        return 80;
    }

    public function seed(): void
    {
        $today = $this->clock->today();
        foreach (DemoAccounts::plans() as $customerId => $planId) {
            if (null === $this->customerPlans->find($customerId)) {
                $this->customerPlans->add(new CustomerPlan($customerId, $planId, $today, Iso::addMonths($today, 1), 'month', $this->clock->now()));
            }
        }
    }
}
