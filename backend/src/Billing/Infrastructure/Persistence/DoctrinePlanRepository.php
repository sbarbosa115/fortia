<?php

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\Plan;
use App\Billing\Domain\Repository\PlanRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Plan> */
final class DoctrinePlanRepository extends DoctrineRepository implements PlanRepository
{
    protected function entityClass(): string
    {
        return Plan::class;
    }

    public function find(string $id): ?Plan
    {
        return $this->findEntity($id);
    }

    public function findByName(string $name): ?Plan
    {
        return $this->repository()->findOneBy(['planName' => $name]);
    }

    public function all(): array
    {
        return $this->repository()->findBy([], ['planName' => 'ASC']);
    }

    public function add(Plan $plan): void
    {
        $this->persist($plan);
    }

    public function remove(Plan $plan): void
    {
        $this->delete($plan);
    }
}
