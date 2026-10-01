<?php

namespace App\Tests\Functional\Api\Commerce;

use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Model\Product;
use App\Commerce\Infrastructure\Scraper\FakeCatalogScraper;
use App\Jobs\Domain\Model\Job;
use App\Tests\Support\ApiTestCase;

/** PRD §7.17 scraping, §8.6 POST /scrapers/products: a job of 1–30 products that persists nothing. */
final class ScrapeJobTest extends ApiTestCase
{
    public function testScrapingReturnsTheStoresProductsAndPersistsNothing(): void
    {
        $owner = $this->account('ACME0001');

        $job = $this->scrape($owner, ['url' => 'coffee.example.com/collections/all']);

        self::assertSame('COMPLETED', $job['status'], (string) json_encode($job));
        self::assertSame('scrape_products', $job['job_type']);
        self::assertSame('scrape_products', $job['result']['type'], '§8.6: result {type: "scrape_products", products}');
        self::assertCount(10, $job['result']['products'], '§8.6: limit defaults to 10');
        $product = $job['result']['products'][0];
        $keys = array_keys($product);
        sort($keys);
        self::assertSame(['description', 'image_url', 'name', 'price', 'product_id', 'product_url'], $keys, '§8.6: the products of the result');
        self::assertSame(['url' => 'https://coffee.example.com/collections/all', 'limit' => 10], $this->scraper()->calls()[0], '§10.5: the scheme is added if missing');
        self::assertSame(0, $this->em()->getRepository(Product::class)->count([]), '§7.17: scraping persists nothing on its own');
        self::assertSame('ACME0001', $this->em()->find(Job::class, $job['job_id'])?->customerId(), 'the job is the caller account\'s');
        self::assertSame('scraping', $job['stage']);
    }

    public function testTheLimitIsBetweenOneAndThirty(): void
    {
        $owner = $this->account('ACME0001');

        self::assertCount(5, $this->scrape($owner, ['url' => 'https://coffee.example.com', 'limit' => 5])['result']['products'], '§10.5: 5 / 10 / 20 / 30');
        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', ['url' => 'https://coffee.example.com', 'limit' => 0], as: $owner), 400, 'VALIDATION_ERROR', '§8.6: limit 1–30');
        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', ['url' => 'https://coffee.example.com', 'limit' => 31], as: $owner), 400, 'VALIDATION_ERROR', '§8.6: limit 1–30');
    }

    public function testTheStoreUrlMustHaveAHostWithADot(): void
    {
        $owner = $this->account('ACME0001');

        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', ['url' => 'localhost'], as: $owner), 400, 'VALIDATION_ERROR', '§10.5: "Please enter a valid store URL"');
        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', [], as: $owner), 400, 'VALIDATION_ERROR', 'url is required');
        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', ['url' => 'https://a.example.com', 'extra' => 1], as: $owner), 400, 'VALIDATION_ERROR', 'no extra fields');
        $this->assertApiError($this->api('POST', '/api/v1/scrapers/products', ['url' => 'https://a.example.com']), 401, 'UNAUTHORIZED');
    }

    public function testAStoreWithoutProductsOrThatCannotBeReadFailsTheJob(): void
    {
        $owner = $this->account('ACME0001');

        $empty = $this->scrape($owner, ['url' => 'https://empty.example.com']);
        self::assertSame('FAILED', $empty['status']);
        self::assertSame('NO_PRODUCTS_FOUND', $empty['result']['error']['type'], '§7.17: fails if none are found');

        $down = $this->scrape($owner, ['url' => 'https://unreachable.example.com']);
        self::assertSame('FAILED', $down['status']);
        self::assertSame('CATALOG_UNREACHABLE', $down['result']['error']['type'], '§10.5: "Could not access URL. Please ensure it is a public store."');
    }

    public function testScrapedDescriptionsAreSanitized(): void
    {
        $owner = $this->account('ACME0001');
        $this->scraper()->willReturn([new CatalogItem('Mug', '<p onclick="steal()">Ceramic <script>alert(1)</script><a href="javascript:x()">link</a></p>', '9.90', null, null)]);

        $product = $this->scrape($owner, ['url' => 'https://mugs.example.com'])['result']['products'][0];

        self::assertStringNotContainsString('<script', $product['description'], 'D11: product HTML is sanitized');
        self::assertStringNotContainsString('onclick', $product['description']);
        self::assertStringNotContainsString('javascript:', $product['description']);
        self::assertStringContainsString('Ceramic', $product['description']);
        self::assertSame(9.9, $product['price']);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed> the job as GET /jobs/{id} shows it (jobs run inside the request in tests)
     */
    private function scrape(string $as, array $body): array
    {
        $started = $this->data($this->api('POST', '/api/v1/scrapers/products', $body, as: $as), 202)['job'];

        return $this->data($this->api('GET', '/api/v1/jobs/'.$started['job_id'], as: $as))['job'];
    }

    private function scraper(): FakeCatalogScraper
    {
        return static::getContainer()->get(FakeCatalogScraper::class);
    }
}
