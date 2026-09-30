<?php

namespace App\Shared\UI\Http\Output\Document;

/** A product as the respondent sees it (PRD §8.6 GET /customer/{id}/products, results). description is HTML. */
final class ProductOutput
{
    public function __construct(
        public readonly string $product_id,
        public readonly string $name,
        public readonly string $description,
        public readonly ?float $price,
        public readonly ?string $image_url,
        public readonly ?string $product_url,
    ) {
    }

    /** @param array<string, mixed> $p */
    public static function fromArray(array $p): self
    {
        return new self(
            (string) ($p['product_id'] ?? ''),
            (string) ($p['name'] ?? ''),
            (string) ($p['description'] ?? ''),
            isset($p['price']) ? (float) $p['price'] : null,
            isset($p['image_url']) ? (string) $p['image_url'] : null,
            isset($p['product_url']) ? (string) $p['product_url'] : null,
        );
    }
}
