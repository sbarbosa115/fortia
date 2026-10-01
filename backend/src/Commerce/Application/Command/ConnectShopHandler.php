<?php

namespace App\Commerce\Application\Command;

use App\Commerce\Domain\Model\ShopifyConnection;
use App\Commerce\Domain\Repository\ShopifyConnectionRepository;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class ConnectShopHandler
{
    public function __construct(
        private readonly ShopifyConnectionRepository $connections,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ConnectShop $command): bool
    {
        $now = $this->clock->now();
        $tokens = $command->tokens;
        $connection = $this->connections->find($command->customerId);
        if (null !== $connection) {
            $connection->reconnect($command->shop, $tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt, $now);

            return false;
        }
        $this->connections->add(new ShopifyConnection($command->customerId, $command->shop, $tokens->accessToken, $tokens->refreshToken, $tokens->expiresAt, $now));

        return true;
    }
}
