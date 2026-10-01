<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\Repository\ProductRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class AttachCatalogHandler
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    public function __invoke(AttachCatalog $command): void
    {
        foreach ($command->productIds as $id) {
            $product = $this->products->find($id);
            if (null !== $product && $product->customerId() === $command->customerId) {
                $product->placeIn($product->sourceUrl(), $command->questionnaireId);
            }
        }
    }
}
