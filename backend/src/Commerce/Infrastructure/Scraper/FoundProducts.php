<?php

namespace App\Commerce\Infrastructure\Scraper;

use App\Commerce\Domain\CatalogItem;

/** The products a scraping run has found so far: at most $limit, without repeating a product URL or a name. */
final class FoundProducts
{
    /** @var list<CatalogItem> */
    private array $items = [];

    /** @var array<string, true> */
    private array $seen = [];

    public function __construct(private readonly int $limit)
    {
    }

    /** @param iterable<CatalogItem> $items */
    public function addAll(iterable $items): void
    {
        foreach ($items as $item) {
            if ($this->full()) {
                return;
            }
            $keys = array_filter(['name:'.mb_strtolower($item->name), null === $item->productUrl ? null : 'url:'.$item->productUrl]);
            if ([] !== array_intersect_key($this->seen, array_flip($keys))) {
                continue;
            }
            foreach ($keys as $key) {
                $this->seen[$key] = true;
            }
            $this->items[] = $item;
        }
    }

    public function has(string $productUrl): bool
    {
        return isset($this->seen['url:'.$productUrl]);
    }

    public function full(): bool
    {
        return \count($this->items) >= $this->limit;
    }

    /** @return list<CatalogItem> */
    public function items(): array
    {
        return $this->items;
    }
}
