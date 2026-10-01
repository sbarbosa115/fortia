<?php

namespace App\Billing\Application\Port;

/** A price of the gateway (PRD §8.13 INVALID_STRIPE_PRICE: it exists, is active, has the interval, amount, currency). */
final class GatewayPrice
{
    public function __construct(
        public readonly string $id,
        public readonly ?int $amount,
        public readonly string $currency,
        /** month | year | null for a one-off price */
        public readonly ?string $interval,
        public readonly bool $active,
        public readonly ?string $productId,
    ) {
    }
}
