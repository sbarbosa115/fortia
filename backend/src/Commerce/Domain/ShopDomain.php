<?php

namespace App\Commerce\Domain;

/** A store of the e-commerce platform (PRD §8.6 GET /auth/shopify: `shop` matching [a-z0-9][a-z0-9-]*\.myshopify\.com). */
final class ShopDomain
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]*\.myshopify\.com$/';

    public static function isValid(?string $shop): bool
    {
        return \is_string($shop) && \strlen($shop) <= 255 && 1 === preg_match(self::PATTERN, $shop);
    }

    /** The store's origin, the catalog's source_url once synced (§7.17). */
    public static function origin(string $shop): string
    {
        return 'https://'.$shop;
    }
}
