<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\CustomerPlan;

interface CustomerPlanRepository
{
    public function find(string $customerId): ?CustomerPlan;

    public function findBySubscription(string $stripeSubscriptionId): ?CustomerPlan;

    public function findByGatewayCustomer(string $stripeCustomerId): ?CustomerPlan;

    public function add(CustomerPlan $plan): void;
}
