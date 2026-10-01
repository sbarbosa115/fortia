<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\Error\ProductNotFound;
use App\Commerce\Domain\Repository\ProductRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteProductHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(DeleteProduct $command): void
    {
        $product = $this->products->find($command->productId);
        if (null === $product || $product->customerId() !== $command->customerId) {
            throw new ProductNotFound();
        }
        $this->products->remove($product);
    }
}
