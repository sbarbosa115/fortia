<?php

namespace App\Commerce\Infrastructure\Platform;

use App\Commerce\Application\Port\CommercePlatform;

/** COMMERCE_PROVIDER picks the adapter: "shopify" talks to Shopify; anything else (the default "fake") runs offline. */
final class CommercePlatformFactory
{
    public function __construct(
        private readonly ShopifyCommercePlatform $shopify,
        private readonly FakeCommercePlatform $fake,
    ) {
    }

    public function create(string $provider): CommercePlatform
    {
        return 'shopify' === $provider ? $this->shopify : $this->fake;
    }
}
