<?php

namespace App\Billing\Application;

/** Where the gateway's hosted pages send the user back (PRD §7.4, §8.3): the console's plans page. */
final class BillingUrls
{
    public function __construct(private readonly string $adminFrontendUrl)
    {
    }

    public function plans(): string
    {
        return rtrim($this->adminFrontendUrl, '/').'/profile/plans';
    }

    public function checkoutSuccess(): string
    {
        return $this->plans().'?checkout=success';
    }

    public function checkoutCancel(): string
    {
        return $this->plans().'?checkout=cancel';
    }
}
