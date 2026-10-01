<?php

namespace App\Commerce\Application\Command;

final class DeleteProduct
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $productId,
    ) {
    }
}
