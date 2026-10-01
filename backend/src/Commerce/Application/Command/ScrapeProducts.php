<?php

namespace App\Commerce\Application\Command;

/** POST /scrapers/products (PRD §8.6): starts the scraping job. Returns its id. */
final class ScrapeProducts
{
    public function __construct(
        public readonly string $customerId,
        public readonly string $url,
        public readonly int $limit,
    ) {
    }
}
