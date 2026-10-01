<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\CatalogItem;

/** Creates ($productId null) or edits a product of an account's catalog (PRD §8.6 product CRUD). Returns its id. */
final class SaveProduct
{
    public function __construct(
        public readonly string $customerId,
        public readonly CatalogItem $item,
        public readonly ?string $productId = null,
    ) {
    }
}
