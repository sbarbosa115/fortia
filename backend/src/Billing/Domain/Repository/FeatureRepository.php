<?php

namespace App\Billing\Domain\Repository;

use App\Billing\Domain\Model\Feature;

interface FeatureRepository
{
    public function find(string $id): ?Feature;

    /** @return list<Feature> ordered by name */
    public function all(): array;

    public function add(Feature $feature): void;

    public function remove(Feature $feature): void;
}
