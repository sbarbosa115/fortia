<?php

declare(strict_types=1);

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\Plan;

interface PlanRepository
{
    public function find(string $id): ?Plan;

    public function findByName(string $name): ?Plan;

    /** @return list<Plan> */
    public function all(): array;

    public function add(Plan $plan): void;

    public function remove(Plan $plan): void;
}
