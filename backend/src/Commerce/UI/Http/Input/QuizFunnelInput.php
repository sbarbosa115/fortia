<?php

namespace App\Commerce\UI\Http\Input;

use App\Commerce\Domain\CatalogItem;
use App\Commerce\Domain\QuizFunnel;
use App\Commerce\Domain\StoreOrigin;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * PRD §8.4 POST /questionnaire/quiz-funnel: type (experience | profiling), source_url? (the store; without it, the
 * connected e-commerce store), products? (those the merchant left on screen: {name, description?, price?,
 * image_url?, product_url?}; a product_id from the scraping result is accepted and ignored).
 */
final class QuizFunnelInput
{
    public const MAX_PRODUCTS = 250;

    #[Assert\NotNull]
    #[Assert\Choice(choices: QuizFunnel::VARIANTS)]
    public ?string $type = null;

    #[Assert\Length(max: 2048)]
    public ?string $source_url = null;

    /** @var list<array<string, mixed>>|null */
    #[OA\Property(type: 'array', nullable: true, items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'price', type: 'number', nullable: true),
        new OA\Property(property: 'image_url', type: 'string', nullable: true),
        new OA\Property(property: 'product_url', type: 'string', nullable: true),
    ]))]
    #[Assert\Count(max: self::MAX_PRODUCTS)]
    public ?array $products = null;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null !== $this->source_url && '' !== trim($this->source_url) && null === StoreOrigin::of($this->source_url)) {
            $context->buildViolation('Please enter a valid store URL.')->atPath('source_url')->addViolation();
        }
        foreach ($this->products ?? [] as $i => $product) {
            if (!\is_array($product) || !\is_string($product['name'] ?? null) || '' === trim($product['name'])) {
                $context->buildViolation('Every product needs a name.')->atPath("products[$i].name")->addViolation();
                continue;
            }
            if (mb_strlen($product['name']) > CatalogItem::NAME_MAX) {
                $context->buildViolation('The product name is too long.')->atPath("products[$i].name")->addViolation();
            }
            foreach (['description', 'image_url', 'product_url', 'product_id'] as $field) {
                if (null !== ($product[$field] ?? null) && !\is_string($product[$field])) {
                    $context->buildViolation('This value should be of type string.')->atPath("products[$i].$field")->addViolation();
                }
            }
            $price = $product['price'] ?? null;
            if (null !== $price && !\is_int($price) && !\is_float($price) && !\is_string($price)) {
                $context->buildViolation('This value should be a number.')->atPath("products[$i].price")->addViolation();
            }
        }
    }

    public function storeUrl(): ?string
    {
        $url = trim((string) $this->source_url);

        return '' === $url ? null : $url;
    }

    /** @return list<array<string, mixed>>|null the products sent, as the PRD's fields; null when none were sent */
    public function products(): ?array
    {
        $items = array_values(array_filter(array_map(
            static fn (mixed $p): ?CatalogItem => \is_array($p) ? CatalogItem::fromArray($p) : null,
            $this->products ?? [],
        )));

        return [] === $items ? null : array_map(static fn (CatalogItem $item): array => $item->toArray(), $items);
    }
}
