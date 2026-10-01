<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Port\ProductHtml;
use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Repository\ProductRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ReplaceCatalogHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductHtml $html,
        private readonly Clock $clock,
    ) {
    }

    /** @return list<string> */
    public function __invoke(ReplaceCatalog $command): array
    {
        $old = $command->wholeAccount
            ? $this->products->listByCustomer($command->customerId)
            : $this->products->listBySource($command->customerId, $command->sourceUrl);
        foreach ($old as $product) {
            $this->products->remove($product);
        }

        $ids = [];
        $at = $this->clock->now();
        foreach ($command->items as $i => $item) {
            // One second apart, so "newest first" listings keep the source's order backwards and ties never shuffle.
            $createdAt = $at->modify(\sprintf('-%d seconds', \count($command->items) - $i));
            $product = new Product(Ids::uuid4(), $command->customerId, $item->name, $createdAt);
            $product->describe($item->name, $this->html->sanitize($item->description), $item->price, $item->imageUrl, $item->productUrl, $at);
            $product->placeIn($command->sourceUrl, null);
            $this->products->add($product);
            $ids[] = $product->productId();
        }

        return $ids;
    }
}
