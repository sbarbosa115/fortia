<?php

namespace App\Commerce\UI\Http\Output;

use OpenApi\Attributes as OA;

/** A product of the account's catalog as the console manages it (PRD §6.11). description is sanitized HTML. */
final class CatalogProductOutput
{
    public function __construct(
        public readonly string $product_id,
        public readonly string $customer_id,
        public readonly string $name,
        #[OA\Property(description: 'Sanitized HTML (D11)')]
        public readonly string $description,
        public readonly ?float $price,
        public readonly ?string $image_url,
        public readonly ?string $product_url,
        #[OA\Property(description: 'The store origin it was imported from')]
        public readonly ?string $source_url,
        #[OA\Property(description: 'The quiz funnel created from it')]
        public readonly ?string $questionnaire_id,
        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {
    }

    /** @param array<string, mixed> $p ProductQueries::productData */
    public static function fromArray(array $p): self
    {
        return new self(
            (string) $p['product_id'],
            (string) $p['customer_id'],
            (string) $p['name'],
            (string) $p['description'],
            isset($p['price']) ? (float) $p['price'] : null,
            $p['image_url'] ?? null,
            $p['product_url'] ?? null,
            $p['source_url'] ?? null,
            $p['questionnaire_id'] ?? null,
            $p['created_at'] ?? null,
            $p['updated_at'] ?? null,
        );
    }
}
