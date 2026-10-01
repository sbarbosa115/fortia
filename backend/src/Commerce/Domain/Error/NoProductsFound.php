<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** Scraping found no product on the store (PRD §7.17: "Fails if none are found"). */
final class NoProductsFound extends Rejected
{
    public function __construct(string $url)
    {
        parent::__construct('NO_PRODUCTS_FOUND', \sprintf('We could not find any product on %s.', $url));
    }
}
