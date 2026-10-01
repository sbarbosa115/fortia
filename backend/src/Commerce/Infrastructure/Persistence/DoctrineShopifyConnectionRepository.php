<?php

namespace App\Commerce\Infrastructure\Persistence;

use App\Commerce\Domain\Model\ShopifyConnection;
use App\Commerce\Domain\Repository\ShopifyConnectionRepository;
use App\Shared\Infrastructure\Persistence\DoctrineRepository;

/** @extends DoctrineRepository<ShopifyConnection> */
final class DoctrineShopifyConnectionRepository extends DoctrineRepository implements ShopifyConnectionRepository
{
    protected function entityClass(): string
    {
        return ShopifyConnection::class;
    }

    public function find(string $customerId): ?ShopifyConnection
    {
        return $this->findEntity($customerId);
    }

    public function add(ShopifyConnection $connection): void
    {
        $this->persist($connection);
    }
}
