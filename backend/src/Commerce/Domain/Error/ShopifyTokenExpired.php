<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The store's token expired and could not be refreshed: the merchant must reconnect (PRD §8.6, Appendix B). */
final class ShopifyTokenExpired extends Rejected
{
    public function __construct()
    {
        parent::__construct('SHOPIFY_TOKEN_EXPIRED', 'Your Shopify connection expired. Reconnect your store.');
    }
}
