<?php

namespace App\Billing\Application\Command;

use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Iso;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class AssignStarterPlanHandler
{
    public function __construct(
        private readonly CustomerPlanRepository $customerPlans,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(AssignStarterPlan $command): void
    {
        $today = $this->clock->today();
        $toAt = Iso::addMonths($today, 1);
        $now = $this->clock->now();
        $existing = $this->customerPlans->find($command->customerId);
        if (null !== $existing) {
            $existing->assign(AssignStarterPlan::PLAN_ID, $today, $toAt, 'month', $now);

            return;
        }
        $this->customerPlans->add(new CustomerPlan($command->customerId, AssignStarterPlan::PLAN_ID, $today, $toAt, 'month', $now));
    }
}
