<?php

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\CustomerPlan;
use App\Billing\Domain\Repository\CustomerPlanRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<CustomerPlan> */
final class DoctrineCustomerPlanRepository extends DoctrineRepository implements CustomerPlanRepository
{
    protected function entityClass(): string
    {
        return CustomerPlan::class;
    }

    public function find(string $customerId): ?CustomerPlan
    {
        return $this->findEntity($customerId);
    }

    public function findBySubscription(string $stripeSubscriptionId): ?CustomerPlan
    {
        return $this->repository()->findOneBy(['stripeSubscriptionId' => $stripeSubscriptionId]);
    }

    public function findByGatewayCustomer(string $stripeCustomerId): ?CustomerPlan
    {
        return $this->repository()->findOneBy(['stripeCustomerId' => $stripeCustomerId]);
    }

    public function add(CustomerPlan $plan): void
    {
        $this->persist($plan);
    }
}
