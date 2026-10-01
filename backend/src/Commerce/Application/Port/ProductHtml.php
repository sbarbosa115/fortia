<?php

namespace App\Commerce\Application\Port;

/**
 * Makes a product description safe to store and show (D11, §14.2: sanitize all HTML coming from products): safe
 * formatting elements only, no scripts, styles, event handlers or javascript: links.
 */
interface ProductHtml
{
    public function sanitize(string $html): string;
}
