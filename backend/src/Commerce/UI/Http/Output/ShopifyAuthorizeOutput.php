<?php

namespace App\Commerce\UI\Http\Output;

/** PRD §8.6 GET /auth/shopify: the URL where the merchant authorizes the app (read-only products scope). */
final class ShopifyAuthorizeOutput
{
    public function __construct(public readonly string $url)
    {
    }
}
