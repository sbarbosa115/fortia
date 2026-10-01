<?php

namespace App\Commerce\Infrastructure\Scraper;

use App\Commerce\Application\Port\CatalogScraper;
use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\Error\CatalogUnreachable;

/**
 * Offline scraping (SCRAPER_PROVIDER=fake, dev and tests): the store's FakeCatalog. A host containing "unreachable"
 * fails like a site that does not answer; one containing "empty" has no products. Tests can script the next answer
 * with willReturn() / willFail().
 */
final class FakeCatalogScraper implements CatalogScraper
{
    /** @var list<CatalogItem>|null */
    private ?array $next = null;
    private bool $fail = false;

    /** @var list<array{url: string, limit: int}> */
    private array $calls = [];

    public function scrape(string $url, int $limit): array
    {
        $this->calls[] = ['url' => $url, 'limit' => $limit];
        $host = strtolower((string) (parse_url($url, \PHP_URL_HOST) ?: $url));
        if ($this->fail || str_contains($host, 'unreachable')) {
            $this->fail = false;
            throw new CatalogUnreachable($url);
        }
        if (null !== $this->next) {
            $items = $this->next;
            $this->next = null;

            return \array_slice($items, 0, $limit);
        }
        if (str_contains($host, 'empty')) {
            return [];
        }

        return FakeCatalog::of($url, $limit);
    }

    /** @param list<CatalogItem> $items the next scrape() answers these (tests) */
    public function willReturn(array $items): void
    {
        $this->next = $items;
    }

    /** The next scrape() fails as unreachable (tests). */
    public function willFail(): void
    {
        $this->fail = true;
    }

    /** @return list<array{url: string, limit: int}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
