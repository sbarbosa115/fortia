<?php

namespace App\Billing\UI\Http\Output;

/** POST /checkout/session: the gateway's hosted checkout page. */
final class CheckoutSessionOutput
{
    public function __construct(public readonly string $checkout_url)
    {
    }
}
