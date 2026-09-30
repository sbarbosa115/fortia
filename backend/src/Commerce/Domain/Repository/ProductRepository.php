<?php

declare(strict_types=1);

namespace App\Commerce\Domain\Repository;

use App\Commerce\Domain\Model\Product;

interface ProductRepository
{
    public function find(string $productId): ?Product;

    /** @return list<Product> */
    public function listByCustomer(string $customerId): array;

    /** @return list<Product> */
    public function listByQuestionnaire(string $questionnaireId): array;

    /** @return list<Product> */
    public function listBySource(string $customerId, string $sourceUrl): array;

    public function add(Product $product): void;

    public function remove(Product $product): void;
}
