<?php

namespace App\Commerce\Application;

use App\Commerce\Application\Command\ReplaceCatalog;
use App\Commerce\Application\Command\StoreShopTokens;
use App\Commerce\Application\Port\CommercePlatform;
use App\Commerce\Domain\Error\ShopifyNotConnected;
use App\Commerce\Domain\Error\ShopifyTokenExpired;
use App\Commerce\Domain\Repository\ShopifyConnectionRepository;
use App\Commerce\Domain\ShopDomain;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Clock;

/**
 * Sync with the e-commerce platform (PRD §7.17, §8.6 GET /shopify/sync/products):
 *
 * - the token is refreshed when there is a refresh token (else an expired one is SHOPIFY_TOKEN_EXPIRED);
 * - the store's products **replace all** of the account's products, each with its first variant's price.
 *
 * It runs outside a transaction: each write is its own command.
 */
final class ShopifySync
{
    public function __construct(
        private readonly ShopifyConnectionRepository $connections,
        private readonly CommercePlatform $platform,
        private readonly CommandBus $commands,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @return int how many products the account now has
     *
     * @throws ShopifyNotConnected
     * @throws ShopifyTokenExpired
     */
    public function sync(string $customerId): int
    {
        $connection = $this->connections->find($customerId) ?? throw new ShopifyNotConnected();
        $shop = $connection->shop();
        $accessToken = $connection->accessToken();

        $refreshToken = $connection->refreshToken();
        if (null !== $refreshToken && '' !== $refreshToken) {
            $tokens = $this->platform->refresh($shop, $refreshToken);
            $this->commands->dispatch(new StoreShopTokens($customerId, $tokens));
            $accessToken = $tokens->accessToken;
        } elseif (null !== $connection->tokenExpiresAt() && $connection->tokenExpiresAt() <= $this->clock->now()) {
            throw new ShopifyTokenExpired();
        }

        $items = $this->platform->products($shop, $accessToken);
        $this->commands->dispatch(new ReplaceCatalog($customerId, $items, ShopDomain::origin($shop), wholeAccount: true));

        return \count($items);
    }
}
