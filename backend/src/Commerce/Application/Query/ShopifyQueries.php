<?php

namespace App\Commerce\Application\Query;

use App\Commerce\Domain\Repository\ShopifyConnectionRepository;

/** The account's connection with the e-commerce platform as the console sees it: the shop, never the tokens. */
final class ShopifyQueries
{
    public function __construct(private readonly ShopifyConnectionRepository $connections)
    {
    }

    public function connectedShop(string $customerId): ?string
    {
        return $this->connections->find($customerId)?->shop();
    }
}
