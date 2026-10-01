<?php

namespace App\Commerce\Domain;

use App\Commerce\Domain\Model\Product;

/**
 * A product as a source gives it before it is stored (a scraped page, the e-commerce platform, the products the
 * merchant left on screen): PRD §6.11 without ids. Built leniently: the name is trimmed and capped, the price parsed
 * from text (Product::parsePrice), and only http(s) URLs are kept. The description is HTML and is sanitized when it
 * is stored (D11).
 */
final class CatalogItem
{
    public const NAME_MAX = 500;
    public const DESCRIPTION_MAX = 20_000;
    public const URL_MAX = 2048;

    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly ?string $price,
        public readonly ?string $imageUrl,
        public readonly ?string $productUrl,
    ) {
    }

    /** null when there is no usable name. */
    public static function from(mixed $name, mixed $description, mixed $price, mixed $imageUrl, mixed $productUrl): ?self
    {
        $name = \is_scalar($name) ? trim(html_entity_decode(strip_tags((string) $name), \ENT_QUOTES | \ENT_HTML5)) : '';
        $name = (string) preg_replace('/\s+/u', ' ', $name);
        if ('' === $name) {
            return null;
        }

        return new self(
            mb_substr($name, 0, self::NAME_MAX),
            mb_substr(\is_scalar($description) ? trim((string) $description) : '', 0, self::DESCRIPTION_MAX),
            Product::parsePrice($price),
            self::url($imageUrl),
            self::url($productUrl),
        );
    }

    /** @param array<string, mixed> $data the PRD's snake_case fields */
    public static function fromArray(array $data): ?self
    {
        return self::from($data['name'] ?? null, $data['description'] ?? null, $data['price'] ?? null, $data['image_url'] ?? null, $data['product_url'] ?? null);
    }

    public static function url(mixed $value): ?string
    {
        $url = \is_string($value) ? trim($value) : '';
        if (str_starts_with($url, '//')) {
            $url = 'https:'.$url;
        }
        if ('' === $url || \strlen($url) > self::URL_MAX || 1 !== preg_match('#^https?://[^\s/?\#]+\.[^\s/?\#]+#i', $url) || 1 === preg_match('/\s/', $url)) {
            return null;
        }

        return $url;
    }

    public function withDescription(string $description): self
    {
        return new self($this->name, $description, $this->price, $this->imageUrl, $this->productUrl);
    }

    /** @return array{name: string, description: string, price: float|null, image_url: string|null, product_url: string|null} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => null === $this->price ? null : (float) $this->price,
            'image_url' => $this->imageUrl,
            'product_url' => $this->productUrl,
        ];
    }
}
