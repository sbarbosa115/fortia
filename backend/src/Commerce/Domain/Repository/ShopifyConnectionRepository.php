<?php

namespace App\Commerce\Domain\Repository;

use App\Commerce\Domain\Model\ShopifyConnection;

interface ShopifyConnectionRepository
{
    public function find(string $customerId): ?ShopifyConnection;

    public function add(ShopifyConnection $connection): void;
}
