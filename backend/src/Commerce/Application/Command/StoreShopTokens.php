<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Port\PlatformTokens;

/** Keeps the refreshed tokens of an account's store (PRD §7.17 "Refreshes the token if there is a refresh token"). */
final class StoreShopTokens
{
    public function __construct(
        public readonly string $customerId,
        public readonly PlatformTokens $tokens,
    ) {
    }
}
