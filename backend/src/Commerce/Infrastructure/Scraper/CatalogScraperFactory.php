<?php

namespace App\Commerce\Infrastructure\Scraper;

use App\Commerce\Application\Port\CatalogScraper;

/** SCRAPER_PROVIDER picks the adapter: "http" reads real stores; anything else (the default "fake") runs offline. */
final class CatalogScraperFactory
{
    public function __construct(
        private readonly SchemaOrgCatalogScraper $http,
        private readonly FakeCatalogScraper $fake,
    ) {
    }

    public function create(string $provider): CatalogScraper
    {
        return 'http' === $provider ? $this->http : $this->fake;
    }
}
