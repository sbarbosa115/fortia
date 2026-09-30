<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Persistence;

use App\Billing\Domain\Model\Feature;
use App\Billing\Domain\Repository\FeatureRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Feature> */
final class DoctrineFeatureRepository extends DoctrineRepository implements FeatureRepository
{
    protected function entityClass(): string
    {
        return Feature::class;
    }

    public function find(string $id): ?Feature
    {
        return $this->findEntity($id);
    }

    public function all(): array
    {
        return array_values($this->repository()->findBy([], ['featureName' => 'ASC']));
    }

    public function add(Feature $feature): void
    {
        $this->persist($feature);
    }

    public function remove(Feature $feature): void
    {
        $this->delete($feature);
    }
}
