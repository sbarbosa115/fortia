<?php

namespace App\Billing\Application\Port;

final class GatewayCoupon
{
    /**
     * @param list<string> $productIds
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?float $percentOff,
        public readonly ?int $amountOff,
        public readonly ?string $currency,
        public readonly string $duration,
        public readonly ?int $durationInMonths,
        public readonly array $productIds,
        public readonly bool $valid,
    ) {
    }
}
