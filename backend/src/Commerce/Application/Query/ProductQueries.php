<?php

namespace App\Commerce\Application\Query;

use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Repository\ProductRepository;
use App\Shared\Domain\Iso;
use App\Shared\Domain\Text;

/** Reads of the catalog for other contexts (product recommendation on submission, PRD §7.7). */
final class ProductQueries
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * The catalog a questionnaire recommends from: its own products first, else the account's (PRD §7.7).
     *
     * @return list<array<string, mixed>>
     */
    public function catalogFor(string $questionnaireId, string $customerId): array
    {
        $products = $this->products->listByQuestionnaire($questionnaireId);
        if ([] === $products) {
            $products = $this->products->listByCustomer($customerId);
        }

        return array_map(self::productData(...), $products);
    }

    /** @return list<array<string, mixed>> the account's whole catalog, oldest first (GET /customer/{id}/products) */
    public function catalogOf(string $customerId): array
    {
        return array_map(self::productData(...), $this->products->listByCustomer($customerId));
    }

    /** @return array<string, mixed>|null one product of the account, null when it is not one of its products */
    public function find(string $customerId, string $productId): ?array
    {
        $product = $this->products->find($productId);

        return null === $product || $product->customerId() !== $customerId ? null : self::productData($product);
    }

    /**
     * The console's listing (/products, PRD §10.19): newest first, every search word in the name (D16, D17).
     *
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function page(string $customerId, string $search, int $page, int $pageSize): array
    {
        $found = $this->products->page($customerId, Text::searchWords($search), $page, $pageSize);

        return ['items' => array_map(self::productData(...), $found['items']), 'total' => $found['total']];
    }

    /** @return array<string, mixed> the PRD §6.11 shape */
    public static function productData(Product $p): array
    {
        return [
            'product_id' => $p->productId(),
            'customer_id' => $p->customerId(),
            'name' => $p->name(),
            'description' => $p->description(),
            'price' => null === $p->price() ? null : (float) $p->price(),
            'image_url' => $p->imageUrl(),
            'product_url' => $p->productUrl(),
            'source_url' => $p->sourceUrl(),
            'questionnaire_id' => $p->questionnaireId(),
            'created_at' => Iso::datetime($p->createdAt()),
            'updated_at' => Iso::datetime($p->updatedAt()),
        ];
    }
}
