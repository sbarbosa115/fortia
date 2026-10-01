<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\CatalogItem;

/**
 * Replaces stored products with $items (PRD §7.17), all placed under $sourceUrl:
 *
 * - quiz funnel creation: the products the merchant left on screen replace the catalog of that store origin;
 * - sync with the e-commerce platform ($wholeAccount): they replace all of the account's products.
 *
 * Returns the new products' ids, in order.
 */
final class ReplaceCatalog
{
    /** @param list<CatalogItem> $items */
    public function __construct(
        public readonly string $customerId,
        public readonly array $items,
        public readonly string $sourceUrl,
        public readonly bool $wholeAccount = false,
    ) {
    }
}
