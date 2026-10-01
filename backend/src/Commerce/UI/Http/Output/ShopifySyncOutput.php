<?php

namespace App\Commerce\UI\Http\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

/** PRD §8.6 GET /shopify/sync/products: the store and the account's catalog after the sync (which replaced it all). */
final class ShopifySyncOutput
{
    /** @param list<CatalogProductOutput> $products */
    public function __construct(
        public readonly string $shop,
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: CatalogProductOutput::class)))]
        public readonly array $products,
    ) {
    }
}
