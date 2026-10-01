<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Port\PlatformTokens;

/**
 * Stores an account's connection with the e-commerce platform after the OAuth callback (PRD §8.6). Returns true on
 * the account's first connection (the callback then syncs its products).
 */
final class ConnectShop
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $shop,
        public readonly PlatformTokens $tokens,
    ) {
    }
}
