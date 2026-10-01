<?php

namespace App\Commerce\Domain;

/**
 * A store's address reduced to its origin (PRD §7.17 quiz funnel step 1, §10.5 "Via website"): the scheme is added
 * when missing (https), the host must contain a dot, and only scheme://host[:port] is kept, lowercase.
 *
 *     StoreOrigin::of('Shop.Example.com/collections/all?x=1')  // "https://shop.example.com"
 *     StoreOrigin::of('localhost')                             // null
 */
final class StoreOrigin
{
    public static function of(?string $url): ?string
    {
        $url = trim((string) $url);
        if ('' === $url || 1 === preg_match('/\s/', $url)) {
            return null;
        }
        if (1 !== preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true) || '' === $host || !str_contains($host, '.')
            || str_starts_with($host, '.') || str_ends_with($host, '.') || 1 !== preg_match('/^[a-z0-9.-]+$/', $host)) {
            return null;
        }

        return $scheme.'://'.$host.(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
