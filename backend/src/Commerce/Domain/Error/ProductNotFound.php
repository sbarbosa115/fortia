<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** No such product in the caller's catalog (another account's product is the same 404). */
final class ProductNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('PRODUCT_NOT_FOUND', 'Product not found.');
    }
}
