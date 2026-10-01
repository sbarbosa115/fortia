<?php

namespace App\Commerce\Infrastructure\Scraper;

use App\Commerce\Application\Port\CatalogScraper;
use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\CatalogUnreachable;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reads a store's catalog over plain HTTP (SCRAPER_PROVIDER=http), in this order until $limit products are found:
 *
 * 1. the schema.org Product data (JSON-LD) of the page itself, including ItemList and @graph wrappers;
 * 2. the store's public `/products.json` (stores of the e-commerce platform publish it);
 * 3. the product pages the page links to on the same host (`/products/…`, `/product/…`, `/p/…`), each read for its
 *    JSON-LD Product, or its Open Graph product tags.
 *
 * The PRD's hosted scraping service (Apify) is replaced by this on purpose (docs/pdr/prd-mappi.md "Left out"): pages
 * that only render their catalog with scripts are not seen. Requests never reach private networks (SSRF), stop
 * after 10 s each and read at most 2 MB; at most 40 product pages are read per run.
 */
final class SchemaOrgCatalogScraper implements CatalogScraper
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_PAGES = 40;
    private const PRODUCT_PATH = '#/(products?|p|item|shop/[^/]+)/[^/?\#]+#i';

    private readonly HttpClientInterface $http;

    /**
     * @param array<string, string> $resolve fixed host → IP answers instead of DNS (tests run offline); empty in the app
     */
    public function __construct(HttpClientInterface $http, private readonly array $resolve = [])
    {
        $this->http = new NoPrivateNetworkHttpClient($http);
    }

    public function scrape(string $url, int $limit): array
    {
        $url = trim($url);
        if (1 !== preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            $url = 'https://'.$url;
        }
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
        $host = strtolower((string) parse_url($url, \PHP_URL_HOST));
        if (!\in_array($scheme, ['http', 'https'], true) || '' === $host) {
            throw new CatalogUnreachable($url);
        }
        $html = $this->fetch($url, 'text/html');
        if (null === $html) {
            throw new CatalogUnreachable($url);
        }
        $origin = $scheme.'://'.$host.(null === parse_url($url, \PHP_URL_PORT) ? '' : ':'.parse_url($url, \PHP_URL_PORT));

        $found = new FoundProducts($limit);
        $document = self::document($html);
        $found->addAll(self::jsonLdProducts($document, $url));

        if (!$found->full()) {
            $found->addAll($this->storeFeed($origin, $limit));
        }
        if (!$found->full()) {
            $pages = 0;
            foreach (self::productLinks($document, $url, $host) as $link) {
                if ($found->full() || $pages++ >= min(self::MAX_PAGES, 2 * $limit)) {
                    break;
                }
                if ($found->has($link)) {
                    continue;
                }
                $page = $this->fetch($link, 'text/html');
                if (null !== $page) {
                    $productDocument = self::document($page);
                    $found->addAll([] !== ($products = self::jsonLdProducts($productDocument, $link)) ? \array_slice($products, 0, 1) : array_filter([self::openGraphProduct($productDocument, $link)]));
                }
            }
        }

        return $found->items();
    }

    /** @return list<CatalogItem> the products of the store's public /products.json, if it has one */
    private function storeFeed(string $origin, int $limit): array
    {
        $json = $this->fetch($origin.'/products.json?limit='.max(1, min(250, $limit)), 'application/json');
        $data = null === $json ? null : json_decode($json, true);
        if (!\is_array($data) || !\is_array($data['products'] ?? null)) {
            return [];
        }
        $items = [];
        foreach ($data['products'] as $product) {
            if (!\is_array($product)) {
                continue;
            }
            $images = \is_array($product['images'] ?? null) ? $product['images'] : [];
            $variants = \is_array($product['variants'] ?? null) ? $product['variants'] : [];
            $handle = \is_string($product['handle'] ?? null) ? $product['handle'] : null;
            $item = CatalogItem::from(
                $product['title'] ?? null,
                $product['body_html'] ?? '',
                \is_array($variants[0] ?? null) ? ($variants[0]['price'] ?? null) : null,
                \is_array($images[0] ?? null) ? ($images[0]['src'] ?? null) : null,
                null === $handle ? null : $origin.'/products/'.rawurlencode($handle),
            );
            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function fetch(string $url, string $accept): ?string
    {
        try {
            $response = $this->http->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 10,
                'max_redirects' => 3,
                'resolve' => $this->resolve,
                'headers' => ['Accept' => $accept.',*/*;q=0.5', 'User-Agent' => 'MappiCatalogReader/1.0'],
            ]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (\strlen($body) > self::MAX_BYTES) {
                    $response->cancel();
                    break;
                }
            }

            return substr($body, 0, self::MAX_BYTES);
        } catch (ExceptionInterface) {
            return null;
        }
    }

    private static function document(string $html): \Dom\HTMLDocument
    {
        return \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR | \Dom\HTML_NO_DEFAULT_NS);
    }

    /** @return list<CatalogItem> */
    public static function jsonLdProducts(\Dom\HTMLDocument $document, string $base): array
    {
        $items = [];
        foreach ($document->querySelectorAll('script[type="application/ld+json"]') as $script) {
            $data = json_decode(trim((string) $script->textContent), true);
            if (!\is_array($data)) {
                continue;
            }
            foreach (self::productNodes($data) as $node) {
                $item = self::fromSchemaProduct($node, $base);
                if (null !== $item) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * Every schema.org Product in a JSON-LD value: the value itself, a list, an @graph, an ItemList's elements.
     *
     * @param array<mixed> $data
     *
     * @return list<array<string, mixed>>
     */
    private static function productNodes(array $data, int $depth = 0): array
    {
        if ($depth > 10) {
            return [];
        }
        if (array_is_list($data)) {
            $nodes = [];
            foreach ($data as $value) {
                if (\is_array($value)) {
                    $nodes = [...$nodes, ...self::productNodes($value, $depth + 1)];
                }
            }

            return $nodes;
        }
        $types = array_map('strval', array_filter((array) ($data['@type'] ?? []), 'is_scalar'));
        if (\in_array('Product', $types, true) || \in_array('ProductGroup', $types, true)) {
            /* @var array<string, mixed> $data */
            return [$data];
        }
        $nodes = [];
        foreach (['@graph', 'itemListElement', 'item', 'mainEntity'] as $key) {
            if (\is_array($data[$key] ?? null)) {
                $nodes = [...$nodes, ...self::productNodes($data[$key], $depth + 1)];
            }
        }

        return $nodes;
    }

    /** @param array<string, mixed> $node */
    private static function fromSchemaProduct(array $node, string $base): ?CatalogItem
    {
        $image = $node['image'] ?? null;
        if (\is_array($image)) {
            $image = array_is_list($image) ? ($image[0] ?? null) : ($image['url'] ?? null);
            if (\is_array($image)) {
                $image = $image['url'] ?? null;
            }
        }
        $variants = \is_array($node['hasVariant'] ?? null) ? array_values($node['hasVariant']) : [];
        $offers = $node['offers'] ?? (\is_array($variants[0] ?? null) ? ($variants[0]['offers'] ?? null) : null);
        if (\is_array($offers) && array_is_list($offers)) {
            $offers = $offers[0] ?? null;
        }
        $price = null;
        if (\is_array($offers)) {
            $specification = \is_array($offers['priceSpecification'] ?? null) ? $offers['priceSpecification'] : [];
            $price = $offers['price'] ?? $offers['lowPrice'] ?? ($specification['price'] ?? null);
        }
        $url = $node['url'] ?? (\is_array($offers) ? ($offers['url'] ?? null) : null);

        return CatalogItem::from(
            $node['name'] ?? null,
            $node['description'] ?? '',
            $price,
            \is_string($image) ? self::absolute($image, $base) : null,
            \is_string($url) ? self::absolute($url, $base) : $base,
        );
    }

    private static function openGraphProduct(\Dom\HTMLDocument $document, string $url): ?CatalogItem
    {
        $meta = static fn (string $property): ?string => $document->querySelector('meta[property="'.$property.'"][content]')?->getAttribute('content');
        $type = strtolower((string) $meta('og:type'));
        if (!str_contains($type, 'product')) {
            return null;
        }
        $image = $meta('og:image');

        return CatalogItem::from($meta('og:title'), $meta('og:description') ?? '', $meta('product:price:amount') ?? $meta('og:price:amount'), null === $image ? null : self::absolute($image, $url), $url);
    }

    /** @return list<string> same-host links that look like product pages, in page order */
    private static function productLinks(\Dom\HTMLDocument $document, string $base, string $host): array
    {
        $links = [];
        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $href = self::absolute((string) $anchor->getAttribute('href'), $base);
            if (null === $href || strtolower((string) parse_url($href, \PHP_URL_HOST)) !== $host) {
                continue;
            }
            $path = (string) parse_url($href, \PHP_URL_PATH);
            if (1 === preg_match(self::PRODUCT_PATH, $path)) {
                $links[strtok($href, '?#') ?: $href] = true;
            }
        }

        return array_keys($links);
    }

    private static function absolute(string $url, string $base): ?string
    {
        $url = trim($url);
        if ('' === $url || str_starts_with($url, 'data:') || str_starts_with($url, 'javascript:') || str_starts_with($url, '#')) {
            return null;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        $scheme = (string) parse_url($base, \PHP_URL_SCHEME);
        $host = (string) parse_url($base, \PHP_URL_HOST);
        $port = parse_url($base, \PHP_URL_PORT);
        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }
        $origin = $scheme.'://'.$host.(null === $port ? '' : ':'.$port);
        if (str_starts_with($url, '/')) {
            return $origin.$url;
        }
        $path = (string) parse_url($base, \PHP_URL_PATH);
        $dir = str_ends_with($path, '/') ? $path : \dirname('' === $path ? '/' : $path).'/';

        return $origin.str_replace('//', '/', $dir.$url);
    }
}
