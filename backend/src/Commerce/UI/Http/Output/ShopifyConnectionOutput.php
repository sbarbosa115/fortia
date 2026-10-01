<?php

namespace App\Commerce\UI\Http\Output;

/** PRD §8.6 GET /shopify/connection: the connected store, or null. */
final class ShopifyConnectionOutput
{
    public function __construct(public readonly ?string $shop)
    {
    }
}
