<?php

namespace App\Commerce\Application\Port;

use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\CatalogUnreachable;

/**
 * Reads a web store's catalog (PRD §7.17 scraping, §13.7): 1–30 products per run. The real adapter reads the store
 * over HTTP (schema.org Product data, never reaching private networks); dev and tests use a deterministic fake
 * (SCRAPER_PROVIDER=fake). It persists nothing.
 */
interface CatalogScraper
{
    /**
     * @return list<CatalogItem> at most $limit, possibly none
     *
     * @throws CatalogUnreachable
     */
    public function scrape(string $url, int $limit): array;
}
