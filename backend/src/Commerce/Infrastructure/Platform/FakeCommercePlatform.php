<?php

namespace App\Commerce\Infrastructure\Platform;

use App\Commerce\Application\Port\CommercePlatform;
use App\Commerce\Application\Port\PlatformTokens;
use App\Commerce\Domain\Error\ShopifyTokenExpired;
use App\Commerce\Domain\Error\TokenExchangeFailed;
use App\Commerce\Infrastructure\Scraper\FakeCatalog;
use App\Shared\Domain\Clock;

/**
 * The offline e-commerce platform (COMMERCE_PROVIDER=fake, dev and tests):
 *
 * - authorization happens on our own page, /fake-shopify/admin/oauth/authorize (FakeShopifyController), whose
 *   "Install app" goes back to the callback with a code "fake-…" (a code that does not start with "fake-", or
 *   "fake-refused", is refused like a bad code);
 * - tokens expire in an hour and refresh while the refresh token starts with "fake-refresh-";
 * - a store's products are its FakeCatalog (10 products), an access token starting with "expired" is refused;
 * - webhooks are signed with FAKE_WEBHOOK_SECRET.
 *
 * Tests can make the next exchange, refresh or product read fail.
 */
final class FakeCommercePlatform implements CommercePlatform
{
    public const FAKE_WEBHOOK_SECRET = 'fake-shopify-webhook-secret';
    public const AUTHORIZE_PATH = '/fake-shopify/admin/oauth/authorize';
    public const CATALOG_SIZE = 10;

    private bool $refuseExchange = false;
    private bool $refuseRefresh = false;
    private bool $refuseProducts = false;

    public function __construct(
        private readonly Clock $clock,
        private readonly string $appUrl,
    ) {
    }

    public function authorizeUrl(string $shop, string $state, string $redirectUri): string
    {
        return rtrim($this->appUrl, '/').self::AUTHORIZE_PATH.'?'.http_build_query([
            'shop' => $shop,
            'scope' => 'read_products',
            'state' => $state,
            'redirect_uri' => $redirectUri,
        ]);
    }

    public function exchangeCode(string $shop, string $code): PlatformTokens
    {
        if ($this->refuseExchange || !str_starts_with($code, 'fake-') || 'fake-refused' === $code) {
            $this->refuseExchange = false;
            throw new TokenExchangeFailed();
        }

        return $this->tokens();
    }

    public function refresh(string $shop, string $refreshToken): PlatformTokens
    {
        if ($this->refuseRefresh || !str_starts_with($refreshToken, 'fake-refresh-')) {
            $this->refuseRefresh = false;
            throw new ShopifyTokenExpired();
        }

        return $this->tokens();
    }

    public function products(string $shop, string $accessToken): array
    {
        if ($this->refuseProducts || str_starts_with($accessToken, 'expired')) {
            $this->refuseProducts = false;
            throw new ShopifyTokenExpired();
        }

        return FakeCatalog::of('https://'.$shop, self::CATALOG_SIZE);
    }

    public function webhookSecret(): string
    {
        return self::FAKE_WEBHOOK_SECRET;
    }

    /** Tests: the next code exchange fails. */
    public function willRefuseExchange(): void
    {
        $this->refuseExchange = true;
    }

    /** Tests: the next refresh fails. */
    public function willRefuseRefresh(): void
    {
        $this->refuseRefresh = true;
    }

    /** Tests: the next product read is refused (expired token). */
    public function willRefuseProducts(): void
    {
        $this->refuseProducts = true;
    }

    private function tokens(): PlatformTokens
    {
        return new PlatformTokens('fake-access-'.bin2hex(random_bytes(8)), 'fake-refresh-'.bin2hex(random_bytes(8)), $this->clock->now()->modify('+1 hour'));
    }
}
