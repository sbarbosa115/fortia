<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Port\ProductHtml;
use App\Commerce\Domain\Error\ProductNotFound;
use App\Commerce\Domain\Model\Product;
use App\Commerce\Domain\Repository\ProductRepository;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Ids;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class SaveProductHandler
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductHtml $html,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SaveProduct $command): string
    {
        $now = $this->clock->now();
        $item = $command->item;
        if (null === $command->productId) {
            $product = new Product(Ids::uuid4(), $command->customerId, $item->name, $now);
            $this->products->add($product);
        } else {
            $product = $this->products->find($command->productId);
            if (null === $product || $product->customerId() !== $command->customerId) {
                throw new ProductNotFound();
            }
        }
        $product->describe($item->name, $this->html->sanitize($item->description), $item->price, $item->imageUrl, $item->productUrl, $now);

        return $product->productId();
    }
}
