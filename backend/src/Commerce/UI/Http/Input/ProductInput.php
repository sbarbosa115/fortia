<?php

namespace App\Commerce\UI\Http\Input;

use App\Commerce\Domain\CatalogItem;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** POST /customer/{id}/products and PUT /customer/{id}/products/{pid} (PRD §8.6, §6.11): the whole product. */
final class ProductInput
{
    #[Assert\NotNull]
    #[Assert\NotBlank(normalizer: 'trim', message: 'Write the product name.')]
    #[Assert\Length(max: CatalogItem::NAME_MAX)]
    public ?string $name = null;

    #[Assert\Length(max: CatalogItem::DESCRIPTION_MAX)]
    #[OA\Property(nullable: true, description: 'HTML; sanitized when stored (D11)')]
    public ?string $description = null;

    #[Assert\PositiveOrZero]
    #[Assert\LessThan(10_000_000_000)]
    public ?float $price = null;

    #[Assert\Length(max: CatalogItem::URL_MAX)]
    public ?string $image_url = null;

    #[Assert\Length(max: CatalogItem::URL_MAX)]
    public ?string $product_url = null;

    #[Assert\Callback]
    public function validateUrls(ExecutionContextInterface $context): void
    {
        foreach (['image_url', 'product_url'] as $field) {
            $value = trim((string) $this->{$field});
            if ('' !== $value && null === CatalogItem::url($value)) {
                $context->buildViolation('Enter a full URL starting with http:// or https://.')->atPath($field)->addViolation();
            }
        }
    }

    public function item(): CatalogItem
    {
        return CatalogItem::from($this->name, $this->description ?? '', $this->price, $this->image_url, $this->product_url)
            ?? throw new \LogicException('A validated product has a name.');
    }
}
