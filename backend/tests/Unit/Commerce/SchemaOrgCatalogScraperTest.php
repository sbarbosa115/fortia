<?php

namespace App\Tests\Unit\Commerce;

use App\Commerce\Domain\Error\CatalogUnreachable;
use App\Commerce\Infrastructure\Scraper\SchemaOrgCatalogScraper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** The HTTP catalog scraper (PRD §7.17, §13.7): schema.org Product data, the store feed, product pages; no SSRF. */
final class SchemaOrgCatalogScraperTest extends TestCase
{
    /** The stores of these tests resolve to a public address (no DNS in tests). */
    private const PUBLIC = ['shop.example.com' => '93.184.216.34', 'cdn.shop.example.com' => '93.184.216.34'];

    public function testProductsComeFromTheJsonLdOfThePage(): void
    {
        $html = <<<'HTML'
            <html><head><script type="application/ld+json">
            {"@context":"https://schema.org","@graph":[
              {"@type":"Organization","name":"Shop"},
              {"@type":"ItemList","itemListElement":[
                {"@type":"ListItem","item":{"@type":"Product","name":"Trail shoe","description":"Grip","image":["/img/trail.jpg"],"url":"/products/trail","offers":{"@type":"Offer","price":"129.00"}}},
                {"@type":"ListItem","item":{"@type":"Product","name":"Road shoe","image":{"url":"https://cdn.shop.example.com/road.jpg"},"offers":[{"@type":"AggregateOffer","lowPrice":99}]}}
              ]}
            ]}
            </script></head><body></body></html>
            HTML;
        $scraper = new SchemaOrgCatalogScraper(new MockHttpClient([new MockResponse($html)]), self::PUBLIC);

        $items = $scraper->scrape('https://shop.example.com/collections/all', 2);

        self::assertCount(2, $items);
        self::assertSame('Trail shoe', $items[0]->name);
        self::assertSame('129.00', $items[0]->price);
        self::assertSame('https://shop.example.com/img/trail.jpg', $items[0]->imageUrl, 'relative URLs become absolute');
        self::assertSame('https://shop.example.com/products/trail', $items[0]->productUrl);
        self::assertSame('99.00', $items[1]->price, 'an AggregateOffer gives its lowest price');
        self::assertSame('https://cdn.shop.example.com/road.jpg', $items[1]->imageUrl);
    }

    public function testTheStoreFeedAndProductPagesFillUpToTheLimit(): void
    {
        $home = '<html><body><a href="/products/mug">Mug</a><a href="/about">About</a><a href="https://elsewhere.example.com/products/x">x</a></body></html>';
        $feed = (string) json_encode(['products' => [['title' => 'Cup', 'body_html' => '<p>Ceramic</p>', 'handle' => 'cup', 'images' => [['src' => 'https://cdn.example.com/cup.jpg']], 'variants' => [['price' => '12.50'], ['price' => '99.00']]]]]);
        $mug = '<html><head><meta property="og:type" content="product"><meta property="og:title" content="Mug"><meta property="product:price:amount" content="9.90"></head></html>';
        $requested = [];
        $client = new MockHttpClient(static function (string $method, string $url) use ($home, $feed, $mug, &$requested): MockResponse {
            $requested[] = $url;

            return match (true) {
                str_contains($url, '/products.json') => new MockResponse($feed),
                str_ends_with($url, '/products/mug') => new MockResponse($mug),
                default => new MockResponse($home),
            };
        });

        $items = (new SchemaOrgCatalogScraper($client, self::PUBLIC))->scrape('shop.example.com', 5);

        self::assertSame(['Cup', 'Mug'], array_map(static fn ($i) => $i->name, $items));
        self::assertSame('12.50', $items[0]->price, '§7.17: only the first variant\'s price is used');
        self::assertSame('https://shop.example.com/products/cup', $items[0]->productUrl);
        self::assertSame('9.90', $items[1]->price, 'a product page without JSON-LD is read from its Open Graph tags');
        self::assertNotContains('https://elsewhere.example.com/products/x', $requested, 'links to other hosts are never followed');
        self::assertSame('https://shop.example.com', explode('/products.json', $requested[1])[0], 'the scheme is added when missing');
    }

    public function testAStoreThatDoesNotAnswerIsUnreachable(): void
    {
        $scraper = new SchemaOrgCatalogScraper(new MockHttpClient([new MockResponse('', ['http_code' => 500])]), self::PUBLIC);

        $this->expectException(CatalogUnreachable::class);
        $scraper->scrape('https://shop.example.com', 10);
    }

    public function testPrivateNetworksAreNeverReached(): void
    {
        $scraper = new SchemaOrgCatalogScraper(new MockHttpClient([new MockResponse('<html></html>')]));

        $this->expectException(CatalogUnreachable::class);
        $scraper->scrape('http://127.0.0.1/admin', 10);
    }
}
