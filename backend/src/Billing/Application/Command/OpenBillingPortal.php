<?php

namespace App\Billing\Application\Command;

/** POST /checkout/portal (PRD §8.3): the gateway's billing portal, returning to the plans page. */
final class OpenBillingPortal
{
    public function __construct(public readonly string $customerId)
    {
    }
}
