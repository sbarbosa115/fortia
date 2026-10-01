<?php

namespace App\Billing\Application\Port;

final class GatewayPromotionCode
{
    /**
     * @param array<string, string> $metadata
     */
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly bool $active,
        public readonly GatewayCoupon $coupon,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?int $maxRedemptions,
        public readonly int $timesRedeemed,
        public readonly array $metadata,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
