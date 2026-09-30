<?php

namespace App\Commerce\Infrastructure\Persistence;

use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Repository\ProductRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<Product> */
final class DoctrineProductRepository extends DoctrineRepository implements ProductRepository
{
    protected function entityClass(): string
    {
        return Product::class;
    }

    public function find(string $productId): ?Product
    {
        return $this->findEntity($productId);
    }

    public function listByCustomer(string $customerId): array
    {
        return $this->repository()->findBy(['customerId' => $customerId], ['createdAt' => 'ASC']);
    }

    public function listByQuestionnaire(string $questionnaireId): array
    {
        return $this->repository()->findBy(['questionnaireId' => $questionnaireId], ['createdAt' => 'ASC']);
    }

    public function listBySource(string $customerId, string $sourceUrl): array
    {
        return $this->repository()->findBy(['customerId' => $customerId, 'sourceUrl' => $sourceUrl], ['createdAt' => 'ASC']);
    }

    public function add(Product $product): void
    {
        $this->persist($product);
    }

    public function remove(Product $product): void
    {
        $this->delete($product);
    }
}
