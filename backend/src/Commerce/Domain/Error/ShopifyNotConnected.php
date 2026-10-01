<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The account has no store connected to the e-commerce platform (PRD §8.6, Appendix B). */
final class ShopifyNotConnected extends Rejected
{
    public function __construct()
    {
        parent::__construct('SHOPIFY_NOT_CONNECTED', 'Your Shopify store is not connected. Authorize it first.');
    }
}
