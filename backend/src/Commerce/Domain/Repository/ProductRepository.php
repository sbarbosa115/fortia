<?php

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

    /**
     * One page of an account's catalog, newest first; every search word must appear in the name (D16, D17).
     *
     * @param list<string> $words
     *
     * @return array{items: list<Product>, total: int}
     */
    public function page(string $customerId, array $words, int $page, int $pageSize): array;

    public function add(Product $product): void;

    public function remove(Product $product): void;
}
