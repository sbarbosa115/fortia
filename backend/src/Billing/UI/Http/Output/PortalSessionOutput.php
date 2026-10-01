<?php

namespace App\Billing\UI\Http\Output;

/** POST /checkout/portal: the gateway's billing portal, returning to the plans page. */
final class PortalSessionOutput
{
    public function __construct(public readonly string $portal_url)
    {
    }
}
