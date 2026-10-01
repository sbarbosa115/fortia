<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Application\Job\QuizFunnelJob;
use App\Commerce\Domain\Error\ShopifyNotConnected;
use App\Commerce\Domain\Repository\ShopifyConnectionRepository;
use App\Commerce\Domain\ShopDomain;
use App\Commerce\Domain\StoreOrigin;
use App\Jobs\Application\Jobs;
use App\Shared\Domain\Error\Rejected;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Step 1 of §7.17 runs here, so a missing store is a 400 at once: the store URL is reduced to its origin. */
#[AsMessageHandler(bus: 'command.bus')]
final class CreateQuizFunnelHandler
{
    public function __construct(
        private readonly ShopifyConnectionRepository $connections,
        private readonly Jobs $jobs,
    ) {
    }

    public function __invoke(CreateQuizFunnel $command): string
    {
        if (null !== $command->storeUrl && '' !== trim($command->storeUrl)) {
            $origin = StoreOrigin::of($command->storeUrl)
                ?? throw new Rejected('INVALID_REQUEST', 'Please enter a valid store URL.');
        } else {
            $connection = $this->connections->find($command->customerId) ?? throw new ShopifyNotConnected();
            $origin = ShopDomain::origin($connection->shop());
        }

        return $this->jobs->start(QuizFunnelJob::TYPE, [
            'customer_id' => $command->customerId,
            'variant' => $command->variant,
            'source_url' => $origin,
            'products' => $command->products,
        ], $command->customerId);
    }
}
