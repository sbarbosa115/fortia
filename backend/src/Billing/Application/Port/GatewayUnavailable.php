<?php

namespace App\Billing\Application\Port;

use App\Shared\Domain\Error\UpstreamFailed;

/** The gateway refused or could not be reached: 502 STRIPE_UNAVAILABLE (PRD Appendix B). */
final class GatewayUnavailable extends UpstreamFailed
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self('STRIPE_UNAVAILABLE', $reason, [], $previous);
    }
}
