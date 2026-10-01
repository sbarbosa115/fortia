<?php

namespace App\Commerce\Application\Job;

use App\Commerce\Application\Port\CatalogScraper;
use App\Commerce\Application\Port\ProductHtml;
use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\NoProductsFound;
use App\Jobs\Application\JobHandler;
use App\Jobs\Application\JobProgress;
use App\Shared\Domain\Ids;

/**
 * Scraping a store's catalog (PRD §7.17, §8.6 POST /scrapers/products), job type `scrape_products`, stage
 * `scraping`. 1–30 products; fails with NO_PRODUCTS_FOUND when there are none, CATALOG_UNREACHABLE when the site
 * cannot be read. Persists nothing.
 *
 * Result: {type: "scrape_products", products: [{product_id, name, description, price, image_url, product_url}]}.
 * The product ids are temporary (for the console's list): nothing is stored until a quiz funnel is created.
 */
final class ScrapeProductsJob implements JobHandler
{
    public const TYPE = 'scrape_products';
    public const MIN = 1;
    public const MAX = 30;
    public const DEFAULT = 10;

    public function __construct(
        private readonly CatalogScraper $scraper,
        private readonly ProductHtml $html,
    ) {
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function handle(array $payload, JobProgress $progress): array
    {
        $url = (string) ($payload['url'] ?? '');
        $limit = max(self::MIN, min(self::MAX, (int) ($payload['limit'] ?? self::DEFAULT)));

        $progress->stage('scraping');
        $items = \array_slice($this->scraper->scrape($url, $limit), 0, $limit);
        if ([] === $items) {
            throw new NoProductsFound($url);
        }

        return [
            'type' => self::TYPE,
            'products' => array_map(fn (CatalogItem $item): array => ['product_id' => Ids::uuid4()] + $item->withDescription($this->html->sanitize($item->description))->toArray(), $items),
        ];
    }
}
