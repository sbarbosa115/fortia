<?php

namespace App\Commerce\Application\Port;

use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\ShopifyTokenExpired;
use App\Commerce\Domain\Error\TokenExchangeFailed;

/**
 * The e-commerce platform (PRD §13.10, Shopify): OAuth with a read-only products scope and expiring tokens, product
 * sync and the HMAC secret of its webhooks. COMMERCE_PROVIDER=shopify talks to the real platform; fake (dev, tests)
 * serves its own authorization page and a deterministic catalog, so everything runs offline.
 */
interface CommercePlatform
{
    /** Where the merchant authorizes the app; the platform then redirects to $redirectUri with code, shop and state. */
    public function authorizeUrl(string $shop, string $state, string $redirectUri): string;

    /** @throws TokenExchangeFailed */
    public function exchangeCode(string $shop, string $code): PlatformTokens;

    /** @throws ShopifyTokenExpired when the refresh token is no longer accepted */
    public function refresh(string $shop, string $refreshToken): PlatformTokens;

    /**
     * The store's products, each with the price of its first variant (§7.17).
     *
     * @return list<CatalogItem>
     *
     * @throws ShopifyTokenExpired when the access token is refused
     */
    public function products(string $shop, string $accessToken): array;

    /** The secret the platform signs its webhooks with ('' = none configured: every webhook is refused). */
    public function webhookSecret(): string;
}
