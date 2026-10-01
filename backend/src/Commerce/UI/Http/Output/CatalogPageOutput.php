<?php

namespace App\Commerce\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** GET /products (the console's /products listing, PRD §10.19, D16/D17): {items, page, page_size, total, total_pages}. */
final class CatalogPageOutput
{
    /** @param list<CatalogProductOutput> $items */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: CatalogProductOutput::class)))]
        public readonly array $items,
        public readonly int $page,
        public readonly int $page_size,
        public readonly int $total,
        public readonly int $total_pages,
    ) {
    }

    /** @param list<array<string, mixed>> $rows */
    public static function of(array $rows, int $page, int $pageSize, int $total): self
    {
        return new self(array_map(CatalogProductOutput::fromArray(...), $rows), $page, $pageSize, $total, (int) ceil($total / max(1, $pageSize)));
    }
}
